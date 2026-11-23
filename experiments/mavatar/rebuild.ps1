$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'env.ps1')
Push-Location (Join-Path $PSScriptRoot 'studio')
try {
    & uv run --with numpy --with pillow --with opencv-python-headless tools/build-layers.py projects/frieren-chest --rig rig.draft.json
    if ($LASTEXITCODE -ne 0) { throw 'Layer build failed.' }
    & uv run tools/variant-requests.py projects/frieren-chest
    if ($LASTEXITCODE -ne 0) { throw 'Variant mask generation failed.' }
    & uv run tools/build-sprites.py projects/frieren-chest
    if ($LASTEXITCODE -ne 0) { throw 'Sprite validation failed; previous runtime archive was kept.' }
    & uv run --with numpy --with pillow --with opencv-python-headless ../build-awkward-overlay.py
    if ($LASTEXITCODE -ne 0) { throw 'Awkward overlay build failed; previous runtime archive was kept.' }
    # Upstream Python atomic staging uses owner-only temporary-file ACLs on Windows.
    # Restore normal project inheritance so the editor and the desktop account can read it.
    & icacls projects/frieren-chest/built /reset /T /C | Select-Object -Last 1
    & icacls projects/frieren-chest/rig.json /reset | Select-Object -Last 1
    & npm run validate-rig -- projects/frieren-chest/rig.json
    if ($LASTEXITCODE -ne 0) { throw 'Rig validation failed.' }
    & npm run render-poses -- projects/frieren-chest
    if ($LASTEXITCODE -ne 0) { throw 'Pose rendering failed.' }
    $mavatarExport = 'projects/frieren-chest/frieren-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff') + '.mavatar'
    & npm run pack-avatar -- projects/frieren-chest --no-source -o $mavatarExport
    if ($LASTEXITCODE -ne 0) { throw 'Avatar export failed.' }
    Copy-Item -LiteralPath $mavatarExport -Destination projects/frieren-chest/frieren-runtime.mavatar -Force
    & node ../check-demo.mjs
    if ($LASTEXITCODE -ne 0) { throw 'Demo checks failed; see review-demo/results.json.' }
} finally {
    Pop-Location
}
