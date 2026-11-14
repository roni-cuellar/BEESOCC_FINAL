$ErrorActionPreference = 'Stop'
$raiz = Join-Path ([IO.Path]::GetTempPath()) ('beesocc_23c_mariadb_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '_' + [Guid]::NewGuid().ToString('N').Substring(0, 8))
if (Test-Path -LiteralPath $raiz) { throw 'El directorio temporal ya existe.' }
if (Get-NetTCPConnection -LocalPort 3317 -State Listen -ErrorAction SilentlyContinue) { throw 'El puerto 3317 está ocupado.' }
New-Item -ItemType Directory -Path $raiz | Out-Null
$datos = (Join-Path $raiz 'data').Replace('\', '/')
$claveRaiz = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')

# Se guardan solo huellas; no se copian datos históricos ni credenciales reales.
$archivos = @(Get-ChildItem -LiteralPath 'C:/xampp/mysql/data/beesocc_v2' -File)
foreach ($ruta in @('C:/xampp/mysql/data/ibdata1', 'C:/xampp/mysql/data/ib_logfile0', 'C:/xampp/mysql/data/ib_logfile1', 'C:/xampp/mysql/bin/my.ini', (Join-Path $PSScriptRoot '../../.env'))) {
    if (Test-Path -LiteralPath $ruta) { $archivos += Get-Item -LiteralPath $ruta }
}
$huellas = @($archivos | ForEach-Object { @{ ruta = $_.FullName; bytes = $_.Length; sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash } })
$huellas | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath (Join-Path $raiz 'principal_antes.json') -Encoding utf8NoBOM

& C:/xampp/mysql/bin/mysql_install_db.exe "--datadir=$datos" '--port=3317' "--password=$claveRaiz"
if ($LASTEXITCODE -ne 0) { throw 'Falló la inicialización del directorio nuevo.' }
$configuracion = @{ host = '127.0.0.1'; port = '3317'; password = $claveRaiz; datadir = $datos }
$configuracion | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $raiz 'mariadb.json') -Encoding utf8NoBOM
# El servidor se inicia después en una sesión controlada; no queda un hijo huérfano.
[PSCustomObject]@{ configuracion_mariadb = (Join-Path $raiz 'mariadb.json'); datadir = $datos; archivos_principal = $huellas.Count } | ConvertTo-Json
