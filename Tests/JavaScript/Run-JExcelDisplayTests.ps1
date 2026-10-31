# Run from any directory: powershell -ExecutionPolicy Bypass -File Tests/JavaScript/Run-JExcelDisplayTests.ps1
param([string]$Browser = "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe")

$ErrorActionPreference = 'Stop'
$core = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$vendor = Split-Path (Split-Path $core)
$temporary = Join-Path $env:TEMP ('exf-display-tests-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temporary | Out-Null
try {
    if (-not (Test-Path $Browser)) { throw 'Pass -Browser with the path to an installed Chromium-based browser.' }
    $source = [IO.File]::ReadAllText((Join-Path $core 'Facades\AbstractAjaxFacade\Elements\JExcelDisplayTrait.php'))
    $json = (ConvertTo-Json -InputObject $source -Compress).Replace('<', '\u003c')
    $tests = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'JExcelDisplay.test.js')) + "`n" +
        [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'JExcelDisplayGrid.test.js'))
    $suites = (New-Object System.Uri((Join-Path $vendor 'npm-asset\jsuites\dist\jsuites.js'))).AbsoluteUri
    $grid = (New-Object System.Uri((Join-Path $vendor 'npm-asset\jspreadsheet-ce\dist\index.js'))).AbsoluteUri
    $colors = (New-Object System.Uri((Join-Path $core 'Facades\AbstractAjaxFacade\js\exfColorTools.js'))).AbsoluteUri
    $html = @"
<!doctype html><html><body><pre id="result">RUNNING</pre>
<script src="$suites"></script><script src="$grid"></script><script src="$colors"></script>
<script>
$tests
try {
    var source = $json;
    document.getElementById('result').textContent = runJExcelDisplayTests(source) + '; ' + runJExcelDisplayGridTests(source);
} catch (error) {
    document.getElementById('result').textContent = 'FAILED: ' + error.stack;
}
</script></body></html>
"@
    $page = Join-Path $temporary 'test.html'
    [IO.File]::WriteAllText($page, $html)
    $uri = (New-Object System.Uri($page)).AbsoluteUri
    $process = Start-Process -FilePath $Browser -ArgumentList @(
        '--headless', '--disable-gpu', '--no-first-run', '--disable-extensions',
        "--user-data-dir=`"$temporary\profile`"", '--dump-dom', $uri
    ) -RedirectStandardOutput (Join-Path $temporary 'output.html') -RedirectStandardError (Join-Path $temporary 'stderr.txt') -Wait -PassThru
    $output = [IO.File]::ReadAllText((Join-Path $temporary 'output.html'))
    $result = [regex]::Match($output, '<pre id="result">([\s\S]*?)</pre>').Groups[1].Value
    if ($process.ExitCode -ne 0 -or $result -notmatch '^\d+ display renderer checks passed; \d+ grid integration checks passed$') {
        throw "Browser checks did not pass: $result"
    }
    Write-Output $result
} finally {
    Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue
}
