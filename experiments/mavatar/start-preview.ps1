param([int]$Port = 9488)
& node (Join-Path $PSScriptRoot 'server.mjs') $Port
exit $LASTEXITCODE
