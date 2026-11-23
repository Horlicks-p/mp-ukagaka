$mavatarExperimentRoot = $PSScriptRoot
$mavatarArtifacts = Join-Path $mavatarExperimentRoot 'artifacts'
$env:PATH = (Join-Path $mavatarArtifacts 'python-tools/bin') + ';' + $env:PATH
# Point uv at a specific interpreter by setting MAVATAR_PYTHON before dot-sourcing this file;
# otherwise uv discovers Python on PATH. Machine paths stay out of the repository.
if ($env:MAVATAR_PYTHON) { $env:UV_PYTHON = $env:MAVATAR_PYTHON }
$env:UV_CACHE_DIR = Join-Path $mavatarArtifacts 'uv-cache'
$env:UV_DEFAULT_INDEX = 'https://pypi.org/simple'
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path $mavatarArtifacts 'browsers'
