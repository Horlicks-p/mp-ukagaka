param([int]$Port = 9488, [string]$Project = 'frieren-chest')
& node (Join-Path $PSScriptRoot 'server.mjs') $Port $Project
exit $LASTEXITCODE
