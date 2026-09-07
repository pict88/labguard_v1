<?php
// LabGuard Monitor - Integrated PHP & MySQL Dashboard
// php -S 127.0.0.1:8000 -t /home/exam/Desktop
// API endpoint for live background polling
if (isset($_GET['api']) && $_GET['api'] === 'status') {
    header('Content-Type: application/json');
    
    $conn = mysqli_connect('localhost', 'exam', 'exam', 'Labguard');
    if (!$conn) {
        echo json_encode(['error' => mysqli_connect_error()]);
        exit;
    }
    
    $sql = "
        SELECT 
            i.ip AS ip_address, 
            i.status AS internet_status, 
            i.time AS internet_time, 
            COALESCE(u.devid, 'None') AS usb_dev_id, 
            COALESCE(u.status, 'no-usb') AS usb_status, 
            COALESCE(u.time, i.time) AS usb_time
        FROM internet i
        LEFT JOIN usb u ON i.ip = u.ip
        ORDER BY i.ip ASC
    ";
    
    $result = mysqli_query($conn, $sql);
    $workstations = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $workstations[] = $row;
        }
    }
    
    mysqli_close($conn);
    echo json_encode($workstations);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LabGuard Monitor</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f9; color: #333; padding: 20px; margin: 0; }
        h2 { margin-top: 0; color: #2c3e50; }
        .summary-container { background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .summary-item { font-size: 0.95em; }
        .summary-item span { font-weight: bold; padding: 3px 8px; border-radius: 4px; margin-left: 5px; }
        .badge-total { background: #e2e8f0; color: #1e293b; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .workstation-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .device-ip-header { font-size: 1.1em; font-weight: bold; color: #1e293b; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
        .module-box { padding: 12px; border-radius: 6px; color: #ffffff; margin-bottom: 10px; font-weight: 500; }
        .module-box:last-child { margin-bottom: 0; }
        .module-title { font-size: 0.75em; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 2px; }
        .module-content { font-size: 0.9em; display: flex; justify-content: space-between; align-items: center; }
        .status-green { background-color: #27ae60 !important; }
        .status-yellow { background-color: #f39c12 !important; }
        .status-red { background-color: #c0392b !important; }
    </style>
</head>
<body>

    <h2>LabGuard: Infrastructure Monitor</h2>

    <div class="summary-container">
        <div class="summary-item">Total Workstations: <span id="sum-total" class="badge-total">0</span></div>
        <div class="summary-item">Online: <span id="sum-online" class="badge-green">0</span></div>
        <div class="summary-item">Isolated: <span id="sum-isolated" class="badge-yellow">0</span></div>
        <div class="summary-item">Offline: <span id="sum-offline" class="badge-red">0</span></div>
        <div class="summary-item">Active USB Alerts: <span id="sum-alerts" class="badge-yellow">0</span></div>
    </div>

    <div id="workstation-grid" class="grid"></div>

    <script>
        function getStatusClass(status) {
            switch(status) {
                case 'online': case 'no-usb': return 'status-green';
                case 'isolated': case 'block': return 'status-yellow';
                case 'offline': case 'authorised': return 'status-red';
                default: return 'status-green';
            }
        }

        function updateDashboard(workstations) {
            const grid = document.getElementById('workstation-grid');
            grid.innerHTML = '';
            
            let total = workstations.length;
            let online = 0, isolated = 0, offline = 0, alerts = 0;

            workstations.forEach(ws => {
                if (ws.internet_status === 'online') online++;
                if (ws.internet_status === 'isolated') isolated++;
                if (ws.internet_status === 'offline') offline++;
                if (ws.usb_status !== 'no-usb') alerts++;

                const netClass = getStatusClass(ws.internet_status);
                const usbClass = getStatusClass(ws.usb_status);
                
                let card = document.createElement('div');
                card.className = 'workstation-card';
                card.innerHTML = `
                    <div class="device-ip-header">${ws.ip_address}</div>
                    <div class="module-box ${netClass}">
                        <div class="module-title">Internet Module</div>
                        <div class="module-content">
                            <span>Status: <strong>${ws.internet_status.toUpperCase()}</strong></span>
                            <span>${ws.internet_time}</span>
                        </div>
                    </div>
                    <div class="module-box ${usbClass}">
                        <div class="module-title">USB Module</div>
                        <div class="module-content" style="flex-direction: column; align-items: flex-start; gap: 2px;">
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <span>Dev: <strong>${ws.usb_dev_id}</strong></span>
                                <span>${ws.usb_time}</span>
                            </div>
                            <div style="font-size: 0.85em; opacity: 0.95;">State: <strong>${ws.usb_status.toUpperCase()}</strong></div>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });

            document.getElementById('sum-total').innerText = total;
            document.getElementById('sum-online').innerText = online;
            document.getElementById('sum-isolated').innerText = isolated;
            document.getElementById('sum-offline').innerText = offline;
            document.getElementById('sum-alerts').innerText = alerts;
        }

        function pollTelemetry() {
            fetch('?api=status')
                .then(res => res.json())
                .then(data => updateDashboard(data))
                .catch(err => console.error('Error fetching telemetry:', err));
        }

        window.onload = function() {
            pollTelemetry();
            setInterval(pollTelemetry, 5000);
        };
    </script>
</body>
</html>
