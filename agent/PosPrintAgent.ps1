<#
.SYNOPSIS
  Print agent for the restaurant POS: prints the server's tickets on a USB printer connected to THIS PC.

.DESCRIPTION
  Polls the POS server (outbound HTTP only: no printer sharing, no open ports) and prints each job RAW (ESC/POS) on the
  Windows printer named in the POS (Impresoras > Estaciones). Reports the result back. Needs only Windows PowerShell 5.1
  (included in Windows 10/11); no installation, no admin rights.

  First run (token comes from the POS: Impresoras > Estaciones de impresion > Nueva estacion):
    powershell -ExecutionPolicy Bypass -File .\PosPrintAgent.ps1 -Server http://192.168.1.10 -Token <token>
  The server and token are saved in agent.json next to this script, so later runs need no arguments.

  -Install     also start it automatically (hidden) at every logon of this Windows user: a shortcut in the user's Startup
               folder, so no administrator rights are needed
  -Uninstall   remove that shortcut
  -DryRunDir   do not print: write every job to this folder instead (to test without a printer)
  -Once        process what is waiting and exit (for tests)
#>
param(
	[string]$Server,
	[string]$Token,
	[int]$WaitSeconds = 15,
	[string]$DryRunDir,
	[switch]$Install,
	[switch]$Uninstall,
	[switch]$Once
)

$ErrorActionPreference = 'Stop'
$Version = '1.0'
$Dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $Dir 'agent.json'
$LogFile = Join-Path $Dir 'agent.log'
$StartupLink = Join-Path ([Environment]::GetFolderPath('Startup')) 'PosPrintAgent.lnk'

function Write-Log([string]$msg){
	$line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg
	Write-Host $line
	try{
		if( (Test-Path $LogFile) -and ((Get-Item $LogFile).Length -gt 1MB) ){ Move-Item $LogFile ($LogFile + '.old') -Force }
		Add-Content -Path $LogFile -Value $line
	}catch{}
}

if( $Uninstall ){
	if( Test-Path $StartupLink ){ Remove-Item $StartupLink -Force }
	Write-Host 'Startup shortcut removed.'
	exit 0
}

# ---- configuration: arguments win and are remembered
if( $Server -and $Token ){
	@{ server = $Server.TrimEnd('/'); token = $Token } | ConvertTo-Json | Set-Content -Path $ConfigFile -Encoding ASCII
}
if( -not (Test-Path $ConfigFile) ){
	Write-Host 'Missing configuration. Run once with: -Server http://<server-ip> -Token <token>'
	exit 1
}
$cfg = Get-Content $ConfigFile -Raw | ConvertFrom-Json
$Server = ([string]$cfg.server).TrimEnd('/')
$Token = [string]$cfg.token

if( $Install ){
	$launch = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}"' -f $MyInvocation.MyCommand.Path
	$lnk = (New-Object -ComObject WScript.Shell).CreateShortcut($StartupLink)
	$lnk.TargetPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
	$lnk.Arguments = $launch
	$lnk.WorkingDirectory = $Dir
	$lnk.WindowStyle = 7
	$lnk.Save()
	Write-Host 'Startup shortcut created: the agent starts at every logon of this Windows user. Starting it now...'
	Start-Process -FilePath $lnk.TargetPath -ArgumentList $launch -WindowStyle Hidden
	exit 0
}

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.ServicePointManager]::SecurityProtocol

# ---- raw printing through the Windows spooler (winspool)
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
using System.ComponentModel;
public class PosRawPrinter {
	[StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
	public class DOCINFO {
		[MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
		[MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
		[MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
	}
	[DllImport("winspool.Drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode, ExactSpelling = true)]
	static extern bool OpenPrinter(string name, out IntPtr h, IntPtr pd);
	[DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true)]
	static extern bool ClosePrinter(IntPtr h);
	[DllImport("winspool.Drv", EntryPoint = "StartDocPrinterW", SetLastError = true, CharSet = CharSet.Unicode, ExactSpelling = true)]
	static extern int StartDocPrinter(IntPtr h, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFO di);
	[DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true)]
	static extern bool EndDocPrinter(IntPtr h);
	[DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true)]
	static extern bool StartPagePrinter(IntPtr h);
	[DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true)]
	static extern bool EndPagePrinter(IntPtr h);
	[DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true)]
	static extern bool WritePrinter(IntPtr h, IntPtr bytes, int count, out int written);

	static string Fail(string what){ return what + ": " + new Win32Exception(Marshal.GetLastWin32Error()).Message; }

	// returns "" on success, else the error text
	public static string Send(string printer, byte[] data, string title){
		IntPtr h;
		if( !OpenPrinter(printer, out h, IntPtr.Zero) ){ return Fail("Cannot open printer '" + printer + "'"); }
		try{
			DOCINFO di = new DOCINFO(); di.pDocName = title; di.pDataType = "RAW";
			if( StartDocPrinter(h, 1, di) == 0 ){ return Fail("StartDocPrinter"); }
			try{
				if( !StartPagePrinter(h) ){ return Fail("StartPagePrinter"); }
				IntPtr p = Marshal.AllocCoTaskMem(data.Length);
				try{
					Marshal.Copy(data, 0, p, data.Length);
					int written;
					if( !WritePrinter(h, p, data.Length, out written) ){ return Fail("WritePrinter"); }
					if( written != data.Length ){ return "Only " + written + " of " + data.Length + " bytes were accepted"; }
				}
				finally{ Marshal.FreeCoTaskMem(p); }
				EndPagePrinter(h);
			}
			finally{ EndDocPrinter(h); }
		}
		finally{ ClosePrinter(h); }
		return "";
	}
}
'@

function Invoke-Api([string]$method, [string]$path, $body, [int]$timeout){
	$h = @{ 'X-Agent-Token' = $Token }
	if( $method -eq 'Get' ){ return Invoke-RestMethod -Method Get -Uri ($Server + $path) -Headers $h -TimeoutSec $timeout }
	return Invoke-RestMethod -Method Post -Uri ($Server + $path) -Headers $h -Body $body -TimeoutSec $timeout
}

function Send-Result($id, [bool]$ok, [string]$err){
	for( $i = 1; $i -le 3; $i++ ){
		try{
			Invoke-Api 'Post' '/print_agent/result' @{ id_job = $id; ok = [int]$ok; error = $err } 20 | Out-Null
			return
		}catch{
			Write-Log ("Could not report job {0} (try {1}): {2}" -f $id, $i, $_.Exception.Message)
			Start-Sleep -Seconds 2
		}
	}
}

function Print-Job($job){

	$bytes = [Convert]::FromBase64String([string]$job.payload)

	if( $DryRunDir ){
		if( -not (Test-Path $DryRunDir) ){ New-Item -ItemType Directory -Path $DryRunDir | Out-Null }
		$safe = ([string]$job.printer) -replace '[^A-Za-z0-9._-]', '_'
		[IO.File]::WriteAllBytes((Join-Path $DryRunDir ('job_{0}_{1}.bin' -f $job.id_job, $safe)), $bytes)
		return ''
	}

	return [PosRawPrinter]::Send([string]$job.printer, $bytes, [string]$job.title)
}

Write-Log ("PosPrintAgent {0} started. Server {1}{2}" -f $Version, $Server, $(if($DryRunDir){' (DRY RUN into ' + $DryRunDir + ')'}else{''}))

$failures = 0
while( $true ){

	try{
		$wait = if( $Once ){ 0 } else { $WaitSeconds }
		$r = Invoke-Api 'Get' ('/print_agent/jobs?wait={0}&version={1}' -f $wait, $Version) $null ($wait + 20)
		$failures = 0

		foreach( $job in @($r.jobs) ){

			if( -not $job ){ continue }
			$err = ''
			try{ $err = Print-Job $job }catch{ $err = $_.Exception.Message }

			if( $err -eq '' ){ Write-Log ("Printed job {0} on '{1}': {2}" -f $job.id_job, $job.printer, $job.title); Send-Result $job.id_job $true '' }
			else{ Write-Log ("FAILED job {0} on '{1}': {2}" -f $job.id_job, $job.printer, $err); Send-Result $job.id_job $false $err }
		}
	}
	catch{

		$failures++
		$status = ''
		try{ $status = [int]$_.Exception.Response.StatusCode }catch{}

		if( $status -eq 401 ){ Write-Log 'The server rejected the token (disabled station or regenerated token). Check agent.json.'; $delay = 30 }
		else{ Write-Log ('Cannot reach the server: ' + $_.Exception.Message); $delay = [Math]::Min(30, 3 * $failures) }

		if( $Once ){ exit 1 }
		Start-Sleep -Seconds $delay
		continue
	}

	if( $Once ){ break }
	if( -not $r.jobs -or @($r.jobs).Count -eq 0 ){ Start-Sleep -Milliseconds 300 }
}
