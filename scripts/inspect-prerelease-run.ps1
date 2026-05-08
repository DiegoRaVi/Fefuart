param(
    [string]$Repo = "DiegoRaVi/Fefuart",
    [string]$WorkflowId = "ci.yml",
    [string]$Ref = "main",
    [string]$RunId,
    [string]$JobName = "Pre-release Rollout Gates",
    [switch]$Wait,
    [int]$PollSeconds = 8,
    [int]$TimeoutMinutes = 30,
    [switch]$FailIfNotSuccess,
    [string]$GitHubToken
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

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

function Invoke-GitHubApi {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Uri,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $headers = @{
        Accept = "application/vnd.github+json"
        Authorization = "Bearer $Token"
        "X-GitHub-Api-Version" = "2022-11-28"
    }

    return Invoke-RestMethod -Method Get -Uri $Uri -Headers $headers
}

function Get-RunById {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$RunId,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $uri = "https://api.github.com/repos/$Repo/actions/runs/$RunId"
    return Invoke-GitHubApi -Uri $uri -Token $Token
}

function Get-LatestWorkflowDispatchRun {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$WorkflowId,
        [Parameter(Mandatory = $true)]
        [string]$Ref,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $encodedRef = [System.Uri]::EscapeDataString($Ref)
    $uri = "https://api.github.com/repos/$Repo/actions/workflows/$WorkflowId/runs?event=workflow_dispatch&branch=$encodedRef&per_page=1"
    $response = Invoke-GitHubApi -Uri $uri -Token $Token
    return $response.workflow_runs | Select-Object -First 1
}

function Get-RunJobs {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Repo,
        [Parameter(Mandatory = $true)]
        [string]$RunId,
        [Parameter(Mandatory = $true)]
        [string]$Token
    )

    $uri = "https://api.github.com/repos/$Repo/actions/runs/$RunId/jobs?per_page=100"
    $response = Invoke-GitHubApi -Uri $uri -Token $Token
    return @($response.jobs)
}

$token = Get-EffectiveGitHubToken -ExplicitToken $GitHubToken
if ([string]::IsNullOrWhiteSpace($token)) {
    throw "Missing GitHub token. Provide -GitHubToken or env:GITHUB_TOKEN."
}

if ([string]::IsNullOrWhiteSpace($RunId)) {
    Write-Host "Resolving latest workflow_dispatch run..." -ForegroundColor Cyan
    $latestRun = Get-LatestWorkflowDispatchRun -Repo $Repo -WorkflowId $WorkflowId -Ref $Ref -Token $token
    if (-not $latestRun) {
        throw "No workflow_dispatch runs found for workflow '$WorkflowId' on ref '$Ref'."
    }

    $RunId = [string]$latestRun.id
}

$deadline = (Get-Date).AddMinutes($TimeoutMinutes)
$run = $null

while ($true) {
    $run = Get-RunById -Repo $Repo -RunId $RunId -Token $token

    if (-not $Wait.IsPresent) {
        break
    }

    if ([string]$run.status -eq "completed") {
        break
    }

    if ((Get-Date) -ge $deadline) {
        throw "Timed out waiting for run $RunId to complete."
    }

    Write-Host "Run $RunId status: $($run.status)" -ForegroundColor Yellow
    Start-Sleep -Seconds $PollSeconds
}

Write-Host "Run URL: $($run.html_url)" -ForegroundColor Green
Write-Host "Run status: $($run.status)"
Write-Host "Run conclusion: $($run.conclusion)"

$jobs = Get-RunJobs -Repo $Repo -RunId $RunId -Token $token
$prereleaseJob = $jobs | Where-Object { $_.name -eq $JobName } | Select-Object -First 1

if (-not $prereleaseJob) {
    $knownJobs = ($jobs | Select-Object -ExpandProperty name) -join ", "
    throw "Could not find job '$JobName' in run $RunId. Available jobs: $knownJobs"
}

$expectedStepNames = @(
    "Run rollout canary suite",
    "Run validation feedback smoke",
    "Publish prerelease result summary"
)

$stepRows = foreach ($stepName in $expectedStepNames) {
    $step = $prereleaseJob.steps | Where-Object { $_.name -eq $stepName } | Select-Object -First 1

    if ($step) {
        [pscustomobject]@{
            step = $step.name
            status = $step.status
            conclusion = $step.conclusion
        }
    }
    else {
        [pscustomobject]@{
            step = $stepName
            status = "missing"
            conclusion = "missing"
        }
    }
}

Write-Host "Prerelease step outcomes:" -ForegroundColor Cyan
$stepRows | Format-Table -AutoSize

$canaryStep = $stepRows | Where-Object { $_.step -eq "Run rollout canary suite" } | Select-Object -First 1
$validationStep = $stepRows | Where-Object { $_.step -eq "Run validation feedback smoke" } | Select-Object -First 1
$summaryStep = $stepRows | Where-Object { $_.step -eq "Publish prerelease result summary" } | Select-Object -First 1

$allSuccess = (
    [string]$run.conclusion -eq "success" -and
    [string]$canaryStep.conclusion -eq "success" -and
    [string]$validationStep.conclusion -eq "success" -and
    [string]$summaryStep.conclusion -eq "success"
)

if ($allSuccess) {
    Write-Host "Prerelease verification OK: canary + validation smoke + summary publish are successful." -ForegroundColor Green
}
else {
    Write-Host "Prerelease verification has non-success outcomes. Review run and step table." -ForegroundColor Yellow
}

if ($FailIfNotSuccess.IsPresent -and -not $allSuccess) {
    throw "FailIfNotSuccess is set and prerelease verification did not pass."
}
