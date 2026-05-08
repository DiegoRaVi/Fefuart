param(
    [Parameter(Mandatory = $true)]
    [string]$GitHubToken,

    [string]$Repository = "DiegoRaVi/Fefuart",
    [string]$WorkflowId = "ci.yml",
    [string]$Ref = "main",

    [ValidateSet("true", "false")]
    [string]$RunPrereleaseGates = "true",

    [ValidateSet("phase1", "phase2", "phase3", "all")]
    [string]$RolloutPhase = "all",

    [string]$RolloutSpaBaseUrl = "",

    [ValidateSet("true", "false")]
    [string]$RunExtendedE2E = "false"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($GitHubToken)) {
    throw "GitHubToken cannot be empty."
}

$dispatchUrl = "https://api.github.com/repos/$Repository/actions/workflows/$WorkflowId/dispatches"

$headers = @{
    Accept = "application/vnd.github+json"
    Authorization = "Bearer $GitHubToken"
    "X-GitHub-Api-Version" = "2022-11-28"
}

$payload = @{
    ref = $Ref
    inputs = @{
        run_prerelease_gates = $RunPrereleaseGates
        rollout_spa_base_url = $RolloutSpaBaseUrl
        rollout_phase = $RolloutPhase
        run_extended_e2e = $RunExtendedE2E
    }
}

Write-Host "Dispatching workflow..."
Write-Host "- Repository: $Repository"
Write-Host "- Workflow: $WorkflowId"
Write-Host "- Ref: $Ref"
Write-Host "- run_prerelease_gates: $RunPrereleaseGates"
Write-Host "- rollout_phase: $RolloutPhase"
Write-Host "- run_extended_e2e: $RunExtendedE2E"
Write-Host "- rollout_spa_base_url: $RolloutSpaBaseUrl"

Invoke-RestMethod -Method Post -Uri $dispatchUrl -Headers $headers -Body ($payload | ConvertTo-Json -Depth 6)

Write-Host "Workflow dispatch request accepted."
Write-Host "Check Actions tab: https://github.com/$Repository/actions/workflows/$WorkflowId"
