#!/bin/bash

if [ -f /etc/lab-config.env ]; then
    source /etc/lab-config.env
else
    echo "[-] CRITICAL: Configuration file /etc/lab-config.env missing!" >&2
    exit 1
fi

if [ ! -d "$FLAG" ]; then
	echo "FLAG not present"
  exit 0
fi

IFACE=$(nmcli -t -f DEVICE,STATE connection show --active | grep ':activated' | grep -v '^lo:' | head -n 1 | cut -d: -f1)
PROFILE=$(nmcli -t -f GENERAL.CONNECTION device show "$IFACE"  | awk -F':' '{print $2}') 1>/dev/null 2>>$ERROR_LOG

function dynamic_network(){	
	ip addr flush dev lo scope global 2>/dev/null || true	
	nmcli connection modify "$PROFILE" ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns "" ipv4.ignore-auto-dns no 1>/dev/null 2>>$ERROR_LOG	
	nmcli connection reload	1>/dev/null 2>>$ERROR_LOG
	nmcli connection down "$PROFILE" 1>/dev/null 2>>$ERROR_LOG || true
	nmcli connection up "$PROFILE" 1>/dev/null 2>>$ERROR_LOG
}

{ curl -S -d "ip=$HELPER_IP&module=network&action=alert&status=LAB_EXIT" http://$SERVER_SOCKET/server.php 1>/dev/null 2>>$ERROR_LOG && echo "[+] Server Updated"; } || echo "[-] ERROR: Server not updated"  

{ rmdir $FLAG 1>/dev/null 2>>$ERROR_LOG && echo "[+] Mode: NORMAL"; } || echo "[-] ERROR: Directory deletion failed [ maybe mode:NORMAL before itself ] [ CHECK LOGS ]"

{ dynamic_network && echo "[+] Network Module Dropped"; } || echo "[-] ERROR: Network Module Drop failed [ check nmcli connections or switch to automatic manually ] [ CHECK LOGS ]"

if ping -c 2 www.google.com &>/dev/null; then
	echo "[+] Internet connectivity restored"
else
	echo "[!] ERROR: No Internet connection"
	echo "   [->] TRY SWITCHING TO AUTOMATIC MANUALLY"
fi
