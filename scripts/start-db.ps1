$tcp = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet -WarningAction SilentlyContinue
if ($tcp) {
    Write-Host "MariaDB is already running on port 3306." -ForegroundColor Green
    exit 0
}

Write-Host "Starting MariaDB on port 3306..." -ForegroundColor Yellow
$bin = "$HOME\.mariadb\server\bin\mariadbd.exe"
$ini = "$HOME\.mariadb\data\my.ini"

Start-Process -FilePath $bin -ArgumentList "--defaults-file=`"$ini`"", "--port=3306", "--console" -NoNewWindow
Start-Sleep -Seconds 2
Write-Host "MariaDB started successfully." -ForegroundColor Green
