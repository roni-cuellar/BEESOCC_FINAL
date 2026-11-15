$ErrorActionPreference = 'Stop'
if (Get-NetTCPConnection -LocalPort 3317 -State Listen -ErrorAction SilentlyContinue) { throw 'Puerto temporal ocupado.' }
$raiz = Join-Path ([IO.Path]::GetTempPath()) ('beesocc_23c_mariadb_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '_' + [Guid]::NewGuid().ToString('N').Substring(0,8))
if (Test-Path -LiteralPath $raiz) { throw 'El directorio ya existe.' }
New-Item -ItemType Directory -Path $raiz | Out-Null
$datos = (Join-Path $raiz 'data').Replace('\','/')
$clave = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
# Inicialización nueva: no se leen ni copian archivos del motor principal activo.
& C:/xampp/mysql/bin/mysql_install_db.exe "--datadir=$datos" '--port=3317' "--password=$clave"
if ($LASTEXITCODE -ne 0) { throw 'No se pudo inicializar el laboratorio.' }
@{host='127.0.0.1';port='3317';password=$clave;datadir=$datos} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $raiz 'mariadb.json') -Encoding utf8NoBOM
[PSCustomObject]@{configuracion_mariadb=(Join-Path $raiz 'mariadb.json');datadir=$datos} | ConvertTo-Json
