[CmdletBinding()]
param(
    [ValidateSet("phase1", "phase2", "phase3", "all")]
    [string]$RolloutPhase = "all",
    [string]$RolloutSpaBaseUrl = "http://127.0.0.1:4173",
    [string]$BackendHost = "127.0.0.1",
    [int]$BackendPort = 8000,
    [switch]$SeedDemoCatalog,
    [switch]$RunExtendedE2E,
    [switch]$SkipValidationSmoke,
    [string]$ArtifactsDir = "artifacts/local-prerelease"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments,
        [Parameter(Mandatory = $true)]
        [string]$WorkingDirectory,
        [hashtable]$Environment = @{}
    )

    Push-Location $WorkingDirectory
    try {
        $envBackup = @{}
        foreach ($key in $Environment.Keys) {
            $envBackup[$key] = [Environment]::GetEnvironmentVariable($key, "Process")
            [Environment]::SetEnvironmentVariable($key, [string]$Environment[$key], "Process")
        }

        & $FilePath @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed with exit code ${LASTEXITCODE}: $FilePath $($Arguments -join ' ')"
        }
    }
    finally {
        foreach ($key in $Environment.Keys) {
            [Environment]::SetEnvironmentVariable($key, $envBackup[$key], "Process")
        }
        Pop-Location
    }
}

function Wait-ForBackendHealth {
    param(
        [Parameter(Mandatory = $true)]
        [string]$HealthUrl,
        [int]$MaxAttempts = 40,
        [int]$DelaySeconds = 2
    )

    for ($i = 1; $i -le $MaxAttempts; $i++) {
        try {
            $response = Invoke-WebRequest -Uri $HealthUrl -Method Get -TimeoutSec 5
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                Write-Host "Backend healthy at $HealthUrl" -ForegroundColor Green
                return
            }
        }
        catch {
            if ($i -eq $MaxAttempts) {
                throw "Backend did not become healthy at $HealthUrl in time."
            }
        }

        Start-Sleep -Seconds $DelaySeconds
    }
}

function Copy-IfExists {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Source,
        [Parameter(Mandatory = $true)]
        [string]$Destination
    )

    if (Test-Path $Source) {
        Copy-Item -Path $Source -Destination $Destination -Recurse -Force
    }
}

function Test-Dependency {
    param([Parameter(Mandatory = $true)][string]$CommandName)

    if (-not (Get-Command $CommandName -ErrorAction SilentlyContinue)) {
        throw "Required command not found: $CommandName"
    }
}

function Get-ArtifactStatusLabel {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (Test-Path $Path) {
        return "present"
    }

    return "missing"
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$serverDir = Join-Path $repoRoot "app/Server"
$spaDir = Join-Path $repoRoot "app/Client/spa"
$artifactRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $ArtifactsDir))
$backendHealthUrl = "http://${BackendHost}:${BackendPort}/api/v1/health"
$backendApiBaseUrl = "http://${BackendHost}:${BackendPort}/api/v1"
$backendLogOut = Join-Path $artifactRoot "laravel-local-prerelease.log"
$backendLogErr = Join-Path $artifactRoot "laravel-local-prerelease.error.log"
$canarySpecRelativePaths = @(
    "e2e/smoke.legacy-rollout.spec.ts",
    "e2e/smoke.auth-cart-liveart.spec.ts",
    "e2e/smoke.cart-orders.spec.ts"
)
$validationSpecRelativePath = "e2e/smoke.validation-feedback.spec.ts"

Test-Dependency -CommandName "php"
Test-Dependency -CommandName "npm"

try {
    $rolloutUri = [Uri]$RolloutSpaBaseUrl
}
catch {
    throw "Invalid RolloutSpaBaseUrl '$RolloutSpaBaseUrl'."
}

if ($rolloutUri.Scheme -notin @("http", "https")) {
    throw "RolloutSpaBaseUrl must use http or https."
}

New-Item -ItemType Directory -Path $artifactRoot -Force | Out-Null

Write-Host "Local pre-release gate configuration:" -ForegroundColor Cyan
Write-Host "- Rollout phase: $RolloutPhase"
Write-Host "- Rollout target URL: $RolloutSpaBaseUrl"
Write-Host "- Backend API: $backendApiBaseUrl"
Write-Host "- Seed demo catalog: $($SeedDemoCatalog.IsPresent)"
Write-Host "- Run extended E2E: $($RunExtendedE2E.IsPresent)"
Write-Host "- Skip validation smoke: $($SkipValidationSmoke.IsPresent)"
Write-Host "- Artifacts dir: $artifactRoot"

$serverProcess = $null
$runStartedAt = Get-Date
$runStatus = "failed"
$canaryStatus = "not-run"
$validationSmokeStatus = if ($SkipValidationSmoke.IsPresent) { "skipped-by-flag" } else { "not-run" }
$demoCatalogStatus = if ($SeedDemoCatalog.IsPresent) { "not-run" } else { "not-requested" }
$defaultCatalogSeedStatus = if ($SeedDemoCatalog.IsPresent) { "not-run" } else { "not-requested" }
$extendedStatus = if ($RunExtendedE2E.IsPresent) { "not-run" } else { "not-requested" }
$canarySpecsPresenceStatus = "not-checked"
$validationSpecPresenceStatus = "not-checked"
$reportIndexStatus = "not-checked"
$failureMessage = $null
$summaryPath = Join-Path $artifactRoot "run-summary.md"
$summaryJsonPath = Join-Path $artifactRoot "run-summary.json"

try {
    Write-Host "Preparing backend test database..." -ForegroundColor Cyan
    $serverEnv = @{
        APP_ENV = "testing"
        DB_CONNECTION = "sqlite"
        DB_DATABASE = "database/database.sqlite"
    }

    $envFile = Join-Path $serverDir ".env"
    if (-not (Test-Path $envFile)) {
        Copy-Item -Path (Join-Path $serverDir ".env.example") -Destination $envFile
    }

    $sqliteFile = Join-Path $serverDir "database/database.sqlite"
    if (-not (Test-Path $sqliteFile)) {
        New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
    }

    Invoke-Step -FilePath "php" -Arguments @("artisan", "migrate", "--force") -WorkingDirectory $serverDir -Environment $serverEnv

    if ($SeedDemoCatalog.IsPresent) {
        Write-Host "Seeding demo catalog data..." -ForegroundColor Cyan
        Invoke-Step -FilePath "php" -Arguments @("artisan", "db:seed", "--class=CatalogDemoSeeder", "--force") -WorkingDirectory $serverDir -Environment $serverEnv
        $demoCatalogStatus = "seeded"

        Write-Host "Seeding demo catalog for default local environment (best effort)..." -ForegroundColor Cyan
        $defaultSeedCommand = "set APP_ENV=& set DB_CONNECTION=& set DB_DATABASE=& php artisan db:seed --class=CatalogDemoSeeder --force"

        try {
            Invoke-Step -FilePath "cmd.exe" -Arguments @("/c", $defaultSeedCommand) -WorkingDirectory $serverDir
            $defaultCatalogSeedStatus = "seeded"
        }
        catch {
            $defaultCatalogSeedStatus = "failed-ignored"
            Write-Host "Default environment demo seeding skipped: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }

    Write-Host "Starting backend server..." -ForegroundColor Cyan
    $serveCommand = "set APP_ENV=$($serverEnv.APP_ENV)&& set DB_CONNECTION=$($serverEnv.DB_CONNECTION)&& set DB_DATABASE=$($serverEnv.DB_DATABASE)&& php artisan serve --host=$BackendHost --port=$BackendPort"
    $serverProcess = Start-Process -FilePath "cmd.exe" -ArgumentList @("/c", $serveCommand) -WorkingDirectory $serverDir -RedirectStandardOutput $backendLogOut -RedirectStandardError $backendLogErr -PassThru

    Wait-ForBackendHealth -HealthUrl $backendHealthUrl

    Write-Host "Checking E2E spec coverage for prerelease execution..." -ForegroundColor Cyan
    $missingCanarySpecs = @()
    foreach ($specRelativePath in $canarySpecRelativePaths) {
        $specFullPath = Join-Path $spaDir $specRelativePath
        if (-not (Test-Path $specFullPath)) {
            $missingCanarySpecs += $specRelativePath
        }
    }

    if ($missingCanarySpecs.Count -gt 0) {
        $canarySpecsPresenceStatus = "missing"
        throw "Missing canary E2E specs: $($missingCanarySpecs -join ', ')"
    }

    $canarySpecsPresenceStatus = "present"

    $validationSpecFullPath = Join-Path $spaDir $validationSpecRelativePath
    if (Test-Path $validationSpecFullPath) {
        $validationSpecPresenceStatus = "present"
    }
    else {
        $validationSpecPresenceStatus = "missing"
        if (-not $SkipValidationSmoke.IsPresent) {
            throw "Missing validation smoke spec required by prerelease runner: $validationSpecRelativePath"
        }
    }

    Write-Host "Running local canary suite..." -ForegroundColor Cyan
    $canaryEnv = @{
        E2E_API_BASE_URL = $backendApiBaseUrl
        E2E_CANARY_ROLLOUT_PHASE = $RolloutPhase
        E2E_ROLLOUT_SPA_BASE_URL = $RolloutSpaBaseUrl
    }

    try {
        Invoke-Step -FilePath "npm" -Arguments @("run", "test:e2e:canary") -WorkingDirectory $spaDir -Environment $canaryEnv
        $canaryStatus = "passed"
    }
    catch {
        $canaryStatus = "failed"
        throw
    }

    if (-not $SkipValidationSmoke.IsPresent) {
        Write-Host "Running local validation feedback smoke..." -ForegroundColor Cyan

        try {
            Invoke-Step -FilePath "npm" -Arguments @("run", "test:e2e:validation") -WorkingDirectory $spaDir -Environment $canaryEnv
            $validationSmokeStatus = "passed"
        }
        catch {
            $validationSmokeStatus = "failed"
            throw
        }
    }

    if ($RunExtendedE2E.IsPresent) {
        if (-not $env:E2E_ASSISTANT_EMAIL -or -not $env:E2E_ASSISTANT_PASSWORD) {
            throw "RunExtendedE2E requires E2E_ASSISTANT_EMAIL and E2E_ASSISTANT_PASSWORD in current shell environment."
        }

        Write-Host "Running local extended suite..." -ForegroundColor Cyan
        $extendedEnv = @{
            E2E_API_BASE_URL = $backendApiBaseUrl
            E2E_ENABLE_MEDIA_UPLOAD = "true"
        }

        Invoke-Step -FilePath "npm" -Arguments @("run", "test:e2e:extended") -WorkingDirectory $spaDir -Environment $extendedEnv
        $extendedStatus = "passed"
    }

    Write-Host "Collecting Playwright artifacts..." -ForegroundColor Cyan
    Copy-IfExists -Source (Join-Path $spaDir "playwright-report") -Destination (Join-Path $artifactRoot "playwright-report")
    Copy-IfExists -Source (Join-Path $spaDir "test-results") -Destination (Join-Path $artifactRoot "test-results")

    $runStatus = "passed"
    Write-Host "Local pre-release gate completed successfully." -ForegroundColor Green
}
catch {
    $failureMessage = $_.Exception.Message
    Write-Host "Local pre-release gate failed: $failureMessage" -ForegroundColor Red
    throw
}
finally {
    if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
        Write-Host "Stopping backend server..." -ForegroundColor Yellow
        Stop-Process -Id $serverProcess.Id -Force
    }

    $runFinishedAt = Get-Date
    $durationSeconds = [Math]::Round(($runFinishedAt - $runStartedAt).TotalSeconds, 2)
    $playwrightReportPath = Join-Path $artifactRoot "playwright-report"
    $playwrightReportIndexPath = Join-Path $playwrightReportPath "index.html"
    $testResultsPath = Join-Path $artifactRoot "test-results"

    $backendStdoutStatus = Get-ArtifactStatusLabel -Path $backendLogOut
    $backendStderrStatus = Get-ArtifactStatusLabel -Path $backendLogErr
    $playwrightReportStatus = Get-ArtifactStatusLabel -Path $playwrightReportPath
    $testResultsStatus = Get-ArtifactStatusLabel -Path $testResultsPath
    $reportIndexStatus = Get-ArtifactStatusLabel -Path $playwrightReportIndexPath

    $artifactsStatus = [ordered]@{
        backend_stdout_log = $backendStdoutStatus
        backend_stderr_log = $backendStderrStatus
        playwright_report = $playwrightReportStatus
        playwright_report_index = $reportIndexStatus
        playwright_test_results = $testResultsStatus
    }

    if ($runStatus -eq "passed" -and $reportIndexStatus -eq "missing") {
        $runStatus = "failed"

        if (-not $failureMessage) {
            $failureMessage = "Expected Playwright HTML report index is missing: playwright-report/index.html"
        }
    }

    $summaryObject = [ordered]@{
        schema_version = "1.0.0"
        status = $runStatus
        started_local = $runStartedAt.ToString('yyyy-MM-dd HH:mm:ss')
        finished_local = $runFinishedAt.ToString('yyyy-MM-dd HH:mm:ss')
        duration_seconds = $durationSeconds
        rollout = [ordered]@{
            phase = $RolloutPhase
            target_url = $RolloutSpaBaseUrl
            backend_api = $backendApiBaseUrl
        }
        seeding = [ordered]@{
            demo_catalog = $demoCatalogStatus
            default_environment_demo = $defaultCatalogSeedStatus
        }
        suites = [ordered]@{
            canary = $canaryStatus
            validation_feedback_smoke = $validationSmokeStatus
            extended = $extendedStatus
        }
        spec_coverage = [ordered]@{
            canary_presence = $canarySpecsPresenceStatus
            validation_presence = $validationSpecPresenceStatus
            canary_expected = $canarySpecRelativePaths
            validation_expected = $validationSpecRelativePath
        }
        artifacts = $artifactsStatus
        failure_detail = $failureMessage
    }

    Set-Content -Path $summaryJsonPath -Value ($summaryObject | ConvertTo-Json -Depth 8) -Encoding utf8
    $summaryJsonStatus = Get-ArtifactStatusLabel -Path $summaryJsonPath

    $summaryLines = @(
        "# Local Pre-release Gate Summary",
        "",
        "- Status: $runStatus",
        "- Started (local): $($runStartedAt.ToString('yyyy-MM-dd HH:mm:ss'))",
        "- Finished (local): $($runFinishedAt.ToString('yyyy-MM-dd HH:mm:ss'))",
        "- Duration seconds: $durationSeconds",
        "- Rollout phase: $RolloutPhase",
        "- Rollout target URL: $RolloutSpaBaseUrl",
        "- Backend API: $backendApiBaseUrl",
        "- Demo catalog seeding: $demoCatalogStatus",
        "- Default environment demo seeding: $defaultCatalogSeedStatus",
        "- Canary specs presence: $canarySpecsPresenceStatus",
        "- Validation spec presence: $validationSpecPresenceStatus",
        "- Canary suite: $canaryStatus",
        "- Validation feedback smoke: $validationSmokeStatus",
        "- Extended suite: $extendedStatus",
        "",
        "## Outputs",
        "- Markdown summary: run-summary.md",
        "- JSON summary: run-summary.json ($summaryJsonStatus)",
        "",
        "## Spec Coverage",
        "- Canary specs expected:",
        "  - e2e/smoke.legacy-rollout.spec.ts",
        "  - e2e/smoke.auth-cart-liveart.spec.ts",
        "  - e2e/smoke.cart-orders.spec.ts",
        "- Validation smoke spec:",
        "  - e2e/smoke.validation-feedback.spec.ts",
        "",
        "## Artifacts",
        "- Backend stdout log (laravel-local-prerelease.log): $backendStdoutStatus",
        "- Backend stderr log (laravel-local-prerelease.error.log): $backendStderrStatus",
        "- Playwright report (playwright-report/): $playwrightReportStatus",
        "- Playwright report index (playwright-report/index.html): $reportIndexStatus",
        "- Playwright test results (test-results/): $testResultsStatus"
    )

    if ($failureMessage) {
        $summaryLines += ""
        $summaryLines += "## Failure Detail"
        $summaryLines += "- $failureMessage"
    }

    Set-Content -Path $summaryPath -Value ($summaryLines -join [Environment]::NewLine) -Encoding utf8
    Write-Host "Run summary written to: $summaryPath" -ForegroundColor Cyan
    Write-Host "Run summary JSON written to: $summaryJsonPath" -ForegroundColor Cyan
}
