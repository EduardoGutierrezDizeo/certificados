<#
.SYNOPSIS
    Arranca worker.py de forma segura, garantizando una sola instancia.

.DESCRIPTION
    Verifica si ya hay un proceso worker.py corriendo. Si lo hay, se niega
    a arrancar uno nuevo y muestra los PIDs existentes. Si no hay ninguno,
    arranca exactamente un worker y muestra su PID. El worker corre como un
    proceso desacoplado, con su salida (stdout/stderr) redirigida a archivos
    de log en `automation/logs/`.

.PARAMETER Stop
    Detiene el (los) proceso(s) worker.py en ejecución.

.PARAMETER Logs
    Muestra las últimas líneas del log del worker (útil para monitorear).

.EXAMPLE
    .\start_worker_safe.ps1          # Arrancar worker (verifica duplicados)
    .\start_worker_safe.ps1 -Stop    # Detener worker
    .\start_worker_safe.ps1 -Logs    # Ver cola del log
#>

param(
    [switch]$Stop,
    [switch]$Logs
)

$ErrorActionPreference = "Stop"
$ScriptRoot = $PSScriptRoot

function Get-WorkerProcesses {
    # Detecta procesos python cuyo CommandLine apunte a worker.py, EXCLUYENDO
    # el propio proceso de PowerShell que corre este script (su command line
    # tambien contiene "worker.py").
    Get-CimInstance Win32_Process |
        Where-Object {
            $_.Name -like "python*" -and
            $_.CommandLine -like "*worker.py*" -and
            $_.ProcessId -ne $PID
        }
}

function Get-LogPath {
    param([string]$Kind)
    $logsDir = Join-Path $ScriptRoot "logs"
    if (-not (Test-Path $logsDir)) {
        New-Item -ItemType Directory -Path $logsDir | Out-Null
    }
    Join-Path $logsDir "worker_$Kind.log"
}

# --- Modo LOGS ---
if ($Logs) {
    $out = Get-LogPath "out"
    $err = Get-LogPath "err"
    if (Test-Path $out) {
        Write-Host "=== worker.out.log (ultimas 40 lineas) ===" -ForegroundColor Cyan
        Get-Content $out -Tail 40
    } else {
        Write-Host "Sin log de salida aun." -ForegroundColor Yellow
    }
    if (Test-Path $err) {
        $errLines = Get-Content $err -Tail 40
        if ($errLines) {
            Write-Host "=== worker.err.log (ultimas 40 lineas) ===" -ForegroundColor Cyan
            $errLines
        }
    }
    exit 0
}

# --- Modo STOP ---
if ($Stop) {
    $workers = Get-WorkerProcesses
    if (-not $workers) {
        Write-Host "No hay ningun worker.py corriendo." -ForegroundColor Yellow
        exit 0
    }

    foreach ($w in $workers) {
        Write-Host "Deteniendo worker.py PID $($w.ProcessId)..." -ForegroundColor Yellow
        Stop-Process -Id $w.ProcessId -Force
    }
    Write-Host "Worker(s) detenido(s)." -ForegroundColor Green
    exit 0
}

# --- Modo START (default) ---
$workers = Get-WorkerProcesses

if ($workers) {
    Write-Host "ERROR: Ya hay un worker.py corriendo:" -ForegroundColor Red
    foreach ($w in $workers) {
        Write-Host "  PID $($w.ProcessId)" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host "Use '.\start_worker_safe.ps1 -Stop' para detenerlo antes de arrancar uno nuevo." -ForegroundColor Yellow
    exit 1
}

Write-Host "No se detectaron workers previos. Arrancando worker.py..." -ForegroundColor Cyan

$venvPython = Join-Path $ScriptRoot "venv\Scripts\python.exe"
if (-not (Test-Path $venvPython)) {
    Write-Host "ERROR: No se encontro Python en $venvPython" -ForegroundColor Red
    exit 1
}

$outLog = Get-LogPath "out"
$errLog = Get-LogPath "err"

# Desacopla el worker de la terminal del llamador: redirige stdout/stderr a
# archivos de log para que siga vivo tras cerrar esta shell y se pueda monitorear.
$proc = Start-Process -FilePath $venvPython -ArgumentList "worker.py" `
    -WorkingDirectory $ScriptRoot -PassThru `
    -RedirectStandardOutput $outLog -RedirectStandardError $errLog

Start-Sleep -Seconds 2

if ($proc.HasExited) {
    Write-Host "ERROR: worker.py termino inmediatamente. Revise el log:" -ForegroundColor Red
    if (Test-Path $errLog) { Get-Content $errLog -Tail 30 }
    exit 1
}

Write-Host "Worker arrancado correctamente. PID: $($proc.Id)" -ForegroundColor Green
Write-Host "Log: $outLog" -ForegroundColor DarkGray
Write-Host "Para ver la salida: .\start_worker_safe.ps1 -Logs" -ForegroundColor DarkGray
Write-Host "Para detenerlo: .\start_worker_safe.ps1 -Stop" -ForegroundColor DarkGray
