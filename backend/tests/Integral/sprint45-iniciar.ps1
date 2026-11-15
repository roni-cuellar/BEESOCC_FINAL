$ErrorActionPreference = 'Stop'
$php = $env:BEESOCC_PHP_BINARY
$instalador = $env:BEESOCC_MARIADB_INSTALL_BINARY
if (-not $php -or -not [IO.Path]::IsPathFullyQualified($php) -or -not (Test-Path -LiteralPath $php -PathType Leaf) -or -not $instalador -or -not [IO.Path]::IsPathFullyQualified($instalador) -or -not (Test-Path -LiteralPath $instalador -PathType Leaf)) {
    throw 'Define BEESOCC_PHP_BINARY y BEESOCC_MARIADB_INSTALL_BINARY con rutas válidas.'
}
# Instancia nueva sin copiar archivos del motor principal ni modificar .env.
$cliente = [Net.Sockets.TcpClient]::new()
try { $cliente.Connect('127.0.0.1', 3317); throw 'Puerto 3317 ocupado: detener.' }
catch [Net.Sockets.SocketException] { }
finally { $cliente.Dispose() }
$raiz = Join-Path ([IO.Path]::GetTempPath()) ('beesocc_23c_mariadb_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '_' + [Guid]::NewGuid().ToString('N').Substring(0,8))
if (Test-Path -LiteralPath $raiz) { throw 'El directorio ya existe.' }
New-Item -ItemType Directory -Path $raiz | Out-Null
$env:BEESOCC_INTEGRAL_DIR = $raiz
& $php (Join-Path $PSScriptRoot 'sprint45-principal.php') antes
if ($LASTEXITCODE -ne 0) { throw 'Falló la referencia de la principal: detener.' }
$datos = (Join-Path $raiz 'data').Replace('\','/')
$clave = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
& $instalador "--datadir=$datos" '--port=3317' "--password=$clave"
if ($LASTEXITCODE -ne 0) { throw 'Falló la inicialización aislada: detener.' }
@{host='127.0.0.1';port='3317';password=$clave;datadir=$datos} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $raiz 'mariadb.json') -Encoding utf8NoBOM
[PSCustomObject]@{configuracion_mariadb=(Join-Path $raiz 'mariadb.json');datadir=$datos} | ConvertTo-Json
