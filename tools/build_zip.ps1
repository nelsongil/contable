param()
$src = 'C:\Users\Nelson\Documents\MEGAsync\desarrollo\contable'
$version = (Get-Content "$src\VERSION" -Raw).Trim()
$out = "$src\contable_v$version.zip"

if (Test-Path $out) { Remove-Item $out }

$excludeDirs = @('.git', '.claude', 'tools', 'backups', '.well-known')
$excludeRootMd = @('CLAUDE.md','AGENTS.md','CONVENTIONS.md','DATABASE.md','SECURITY.md','CHANGELOG.md','README.md')

$files = Get-ChildItem -Path $src -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring($src.Length + 1)
    $skip = $false

    # Excluir directorios de desarrollo
    foreach ($d in $excludeDirs) {
        if ($rel.StartsWith("$d\") -or $rel.StartsWith("$d/")) { $skip = $true; break }
    }

    # Excluir archivos específicos
    if ($rel -eq 'config\database.php' -or $rel -eq 'config/database.php') { $skip = $true }
    if ($rel -eq 'config\.installed' -or $rel -eq 'config/.installed') { $skip = $true }
    if ($rel -match '\.(zip|gitignore|htaccess)$') { $skip = $true }
    if ($rel -match '(^|\\|/)error_log$') { $skip = $true }
    if ($rel -match '\.(DS_Store|db)$' -and $_.Name -in @('.DS_Store','Thumbs.db')) { $skip = $true }

    # Excluir .md de la raíz
    if ($_.DirectoryName -eq $src -and $_.Name -in $excludeRootMd) { $skip = $true }

    -not $skip
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($out, 'Create')
foreach ($f in $files) {
    $rel = $f.FullName.Substring($src.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $rel) | Out-Null
}
$zip.Dispose()

$sizeMB = [Math]::Round((Get-Item $out).Length / 1MB, 2)
Write-Host "ZIP creado: $out ($($files.Count) archivos, ${sizeMB} MB)"
