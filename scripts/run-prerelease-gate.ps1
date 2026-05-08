param(
    [string]$Repo = "DiegoRaVi/Fefuart",
    [string]$Ref = "main",
    [Parameter(Mandatory = $true)]
    [string]$RolloutSpaBaseUrl,
    [ValidateSet("phase1", "phase2", "phase3", "all")]
    [string]$RolloutPhase = "all",
    [switch]$RunExtendedE2E,
    [switch]$Watch,
    [switch]$DownloadArtifacts,
    [string]$ArtifactsDir = "artifacts/prerelease-gate",
    [ValidateSet("auto", "gh", "rest")]
    [string]$Mode = "auto",
    [string]$GitHubToken,
    [switch]$PassThru
)

function Invoke-GhCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Args
    )

    $result = & gh @Args
    if ($LASTEXITCODE -ne 0) {
        throw "gh command failed: gh $($Args -join ' ')"
    }

    return $result
}

function Get-EffectiveGitHubToken {
    param(
        [string]$ExplicitToken
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitToken)) {
        return $ExplicitToken
    }

    if (-not [string]::IsNullOrWhiteSpace($env:GITHUB_TOKEN)) {
        return $env:GITHUB_TOKEN
    }

    return $null
}

function Test-GhReady {
    $gh = Get-Command gh -ErrorAction SilentlyContinue
    if (-not $gh) {
        return $false
    }

    & gh auth status --hostname github.com *> $null
    return ($LASTEXITCODE -eq 0)
}

function Invoke-GitHubApiJson {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet("GET", "POST")]
        [string]$Method,
        [Parameter(Mandatory = $true)]
        [string]$Uri,
        [Parameter(Mandatory = $true)]
        [string]$Token,
        [object]$Body
    )

    $headers = @{
        Accept = "application/vnd.github+json"
        Authorization = "Bearer $Token"
        "X-GitHub-Api-Version" = "2022-11-28"
    }

    try {
        if ($PSBoundParameters.ContainsKey("Body")) {
            return Invoke-RestMethod -Method $Method -Uri $Uri -Headers $headers -Body ($Body | ConvertTo-Json -Depth 10) -ErrorAction Stop
        }

        return Invoke-RestMethod -Method $Method -Uri $Uri -Headers $headers -ErrorAction Stop
    }
    catch {
        $statusCode = $null
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }

        $detail = $_.ErrorDetails.Message
        if ([string]::IsNullOrWhiteSpace($detail)) {
            $detail = $_.Exception.Message
        }

        if ($statusCode) {
            throw "GitHub API request failed ($Method $Uri, HTTP $statusCode): $detail"
        }

        throw "GitHub API request failed ($Method $Uri): $detail"
    }
}

function Invoke-GitHubApiDownload {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Uri,
        [Parameter(Mandatory = $true)]
        [string]$Token,
        [Parameter(Mandatory = $true)]
        [string]$OutFile
    )

    $headers = @{
        Accept = "application/vnd.github+json"
        Authorization = "Bearer $Token"
        "X-GitHub-Api-Version" = "2022-11-28"
    }

    try {
        Invoke-WebRequest -Method Get -Uri $Uri -Headers $headers -OutFile $OutFile -ErrorAction Stop | Out-Null
    }
    catch {
        $statusCode = $null
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }

        $detail = $_.ErrorDetails.Message
        if ([string]::IsNullOrWhiteSpace($detail)) {
            $detail = $_.Exception.Message
        }

        if ($statusCode) {
            throw "GitHub artifact download failed (GET $Uri, HTTP $statusCode): $detail"
        }

        throw "GitHub artifact download failed (GET $Uri): $detail"
    }
}

function Get-LatestRunViaGh {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$Ref
    )

    $latestRunArgs = @(
        "run", "list",
        "--workflow", "ci.yml",
        "--repo", $Repo,
        "--branch", $Ref,
        "--event", "workflow_dispatch",
        "--limit", "1",
        "--json", "databaseId,status,conclusion,url,displayTitle,createdAt"
    )

    $latestRunJson = Invoke-GhCommand -Args $latestRunArgs
    return ($latestRunJson | ConvertFrom-Json | Select-Object -First 1)
}

function Get-LatestRunViaRest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$Ref,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $encodedRef = [System.Uri]::EscapeDataString($Ref)
    $uri = "https://api.github.com/repos/$Repo/actions/workflows/ci.yml/runs?branch=$encodedRef&event=workflow_dispatch&per_page=1"
    $response = Invoke-GitHubApiJson -Method GET -Uri $uri -Token $Token
    $latest = $response.workflow_runs | Select-Object -First 1

    if (-not $latest) {
        return $null
    }

    return [pscustomobject]@{
        databaseId = [string]$latest.id
        status = [string]$latest.status
        conclusion = [string]$latest.conclusion
        url = [string]$latest.html_url
        displayTitle = [string]$latest.display_title
        createdAt = [string]$latest.created_at
    }
}

function Get-LatestRunWithRetry {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet("gh", "rest")]
        [string]$ExecutionMode,
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$Ref,
        [string]$Token,
        [int]$MaxAttempts = 20,
        [int]$DelaySeconds = 3
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        $run = if ($ExecutionMode -eq "gh") {
            Get-LatestRunViaGh -Repo $Repo -Ref $Ref
        }
        else {
            Get-LatestRunViaRest -Repo $Repo -Ref $Ref -Token $Token
        }

        if ($run) {
            return $run
        }

        if ($attempt -lt $MaxAttempts) {
            Start-Sleep -Seconds $DelaySeconds
        }
    }

    return $null
}

function Watch-RunViaRest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$RunId,
        [Parameter(Mandatory = $true)]
        [string]$Token,
        [int]$PollSeconds = 8
    )

    $lastStatus = ""
    $lastConclusion = ""

    while ($true) {
        $uri = "https://api.github.com/repos/$Repo/actions/runs/$RunId"
        $run = Invoke-GitHubApiJson -Method GET -Uri $uri -Token $Token

        $status = [string]$run.status
        $conclusion = [string]$run.conclusion

        if ($status -ne $lastStatus -or $conclusion -ne $lastConclusion) {
            Write-Host "Run status: $status | conclusion: $conclusion"
            $lastStatus = $status
            $lastConclusion = $conclusion
        }

        if ($status -eq "completed") {
            if ($conclusion -ne "success") {
                throw "Workflow run $RunId finished with conclusion '$conclusion'."
            }

            Write-Host "Workflow run completed successfully." -ForegroundColor Green
            return
        }

        Start-Sleep -Seconds $PollSeconds
    }
}

function Download-RunArtifactsViaRest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$RunId,
        [Parameter(Mandatory = $true)]
        [string]$Token,
        [Parameter(Mandatory = $true)]
        [string]$TargetDir
    )

    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null

    $uri = "https://api.github.com/repos/$Repo/actions/runs/$RunId/artifacts?per_page=100"
    $response = Invoke-GitHubApiJson -Method GET -Uri $uri -Token $Token
    $artifacts = @($response.artifacts)

    if ($artifacts.Count -eq 0) {
        Write-Host "No artifacts found for run $RunId." -ForegroundColor Yellow
        return
    }

    foreach ($artifact in $artifacts) {
        $artifactName = [string]$artifact.name
        $artifactId = [string]$artifact.id
        $archiveUrl = [string]$artifact.archive_download_url
        $zipPath = Join-Path $TargetDir ("$artifactName-$artifactId.zip")
        $extractDir = Join-Path $TargetDir $artifactName

        Write-Host "Downloading artifact '$artifactName'..." -ForegroundColor Cyan
        Invoke-GitHubApiDownload -Uri $archiveUrl -Token $Token -OutFile $zipPath

        if (Test-Path $extractDir) {
            Remove-Item -Path $extractDir -Recurse -Force
        }

        Expand-Archive -Path $zipPath -DestinationPath $extractDir -Force
        Remove-Item -Path $zipPath -Force
    }

    Write-Host "Artifacts downloaded successfully." -ForegroundColor Green
}

function Show-LatestRunsViaRest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $uri = "https://api.github.com/repos/$Repo/actions/workflows/ci.yml/runs?per_page=5"
    $response = Invoke-GitHubApiJson -Method GET -Uri $uri -Token $Token

    $rows = @($response.workflow_runs) | Select-Object -First 5 | ForEach-Object {
        [pscustomobject]@{
            id = $_.id
            status = $_.status
            conclusion = $_.conclusion
            created_at = $_.created_at
            url = $_.html_url
        }
    }

    $rows | Format-Table -AutoSize
}

try {
    $rolloutUri = [Uri]$RolloutSpaBaseUrl
}
catch {
    throw "Invalid RolloutSpaBaseUrl '$RolloutSpaBaseUrl'."
}

if ($rolloutUri.Scheme -notin @("http", "https")) {
    throw "RolloutSpaBaseUrl must use http or https."
}

$extendedFlag = if ($RunExtendedE2E.IsPresent) { "true" } else { "false" }
$shouldWatch = $Watch.IsPresent -or $DownloadArtifacts.IsPresent
$token = Get-EffectiveGitHubToken -ExplicitToken $GitHubToken
$ghReady = Test-GhReady

$executionMode = switch ($Mode) {
    "gh" {
        if (-not $ghReady) {
            throw "Mode=gh was requested, but gh is not installed/authenticated. Run 'gh auth login' first or use Mode=rest with a token."
        }

        "gh"
    }
    "rest" {
        if ([string]::IsNullOrWhiteSpace($token)) {
            throw "Mode=rest requires -GitHubToken or env:GITHUB_TOKEN."
        }

        "rest"
    }
    default {
        if ($ghReady) {
            "gh"
        }
        elseif (-not [string]::IsNullOrWhiteSpace($token)) {
            "rest"
        }
        else {
            throw "No GitHub execution mode available. Install/authenticate gh or provide -GitHubToken (or env:GITHUB_TOKEN) for REST mode."
        }
    }
}

Write-Host "Dispatching CI prerelease gate..." -ForegroundColor Cyan
Write-Host "Repo: $Repo"
Write-Host "Ref: $Ref"
Write-Host "Rollout URL: $RolloutSpaBaseUrl"
Write-Host "Rollout phase: $RolloutPhase"
Write-Host "Run extended E2E: $extendedFlag"
Write-Host "Execution mode: $executionMode"
Write-Host "Watch workflow run: $shouldWatch"
Write-Host "Download artifacts: $($DownloadArtifacts.IsPresent)"

if ($executionMode -eq "gh") {
    $dispatchArgs = @(
        "workflow", "run", "ci.yml",
        "--repo", $Repo,
        "--ref", $Ref,
        "-f", "run_prerelease_gates=true",
        "-f", "rollout_spa_base_url=$RolloutSpaBaseUrl",
        "-f", "rollout_phase=$RolloutPhase",
        "-f", "run_extended_e2e=$extendedFlag"
    )

    Invoke-GhCommand -Args $dispatchArgs | Out-Null
}
else {
    $dispatchUri = "https://api.github.com/repos/$Repo/actions/workflows/ci.yml/dispatches"
    $dispatchBody = @{
        ref = $Ref
        inputs = @{
            run_prerelease_gates = "true"
            rollout_spa_base_url = $RolloutSpaBaseUrl
            rollout_phase = $RolloutPhase
            run_extended_e2e = $extendedFlag
        }
    }

    try {
        Invoke-GitHubApiJson -Method POST -Uri $dispatchUri -Token $token -Body $dispatchBody | Out-Null
    }
    catch {
        throw "Failed to dispatch workflow via REST. Verify token permissions (Actions: write) and workflow id 'ci.yml'. Details: $($_.Exception.Message)"
    }
}

Write-Host "Workflow dispatched successfully." -ForegroundColor Green

try {
    $latestRun = Get-LatestRunWithRetry -ExecutionMode $executionMode -Repo $Repo -Ref $Ref -Token $token
}
catch {
    throw "Failed to query workflow runs after dispatch. Verify token permissions (Actions: read). Details: $($_.Exception.Message)"
}

if (-not $latestRun) {
    throw "Workflow dispatched but latest run could not be resolved automatically. Check GitHub Actions manually."
}

$runId = [string]$latestRun.databaseId
$runUrl = [string]$latestRun.url

Write-Host "Run ID: $runId" -ForegroundColor Green
Write-Host "Run URL: $runUrl" -ForegroundColor Green
Write-Host "Initial status: $($latestRun.status)"

if ($shouldWatch) {
    Write-Host "Watching workflow run until completion..." -ForegroundColor Cyan
    if ($executionMode -eq "gh") {
        & gh run watch $runId --repo $Repo --exit-status

        if ($LASTEXITCODE -ne 0) {
            throw "Workflow run $runId failed or was canceled."
        }

        Write-Host "Workflow run completed successfully." -ForegroundColor Green
    }
    else {
        Watch-RunViaRest -Repo $Repo -RunId $runId -Token $token
    }
}

if ($DownloadArtifacts.IsPresent) {
    $targetDir = [System.IO.Path]::GetFullPath((Join-Path (Get-Location) $ArtifactsDir))

    Write-Host "Downloading run artifacts to: $targetDir" -ForegroundColor Cyan
    if ($executionMode -eq "gh") {
        New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        & gh run download $runId --repo $Repo --dir $targetDir

        if ($LASTEXITCODE -ne 0) {
            throw "Failed to download artifacts for run $runId."
        }

        Write-Host "Artifacts downloaded successfully." -ForegroundColor Green
    }
    else {
        Download-RunArtifactsViaRest -Repo $Repo -RunId $runId -Token $token -TargetDir $targetDir
    }
}

if (-not $PassThru.IsPresent) {
    Write-Host "Latest CI runs:" -ForegroundColor Cyan
    if ($executionMode -eq "gh") {
        & gh run list --workflow ci.yml --repo $Repo --limit 5
    }
    else {
        Show-LatestRunsViaRest -Repo $Repo -Token $token
    }
}

if ($PassThru.IsPresent) {
    [pscustomobject]@{
        repo = $Repo
        ref = $Ref
        run_id = $runId
        run_url = $runUrl
        execution_mode = $executionMode
        rollout_phase = $RolloutPhase
        rollout_spa_base_url = $RolloutSpaBaseUrl
    }
}
