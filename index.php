<?php
require 'config.php';

$db = connectDB();

// Fetch all known stations
$stations_result = $db->query("SELECT DISTINCT station_id FROM sensor_data ORDER BY station_id");
$stations = [];
while ($row = $stations_result->fetch_assoc()) {
    $stations[] = $row['station_id'];
}

$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : ($stations[0] ?? 1);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fish Farm Monitor</title>
</head>
<body>

<h1>Fish Farm Monitoring System</h1>

<!-- Station selector -->
<label for="stationSelect">Station:</label>
<select id="stationSelect">
    <?php foreach ($stations as $sid): ?>
        <option value="<?php echo $sid; ?>" <?php echo $sid == $station_id ? 'selected' : ''; ?>>
            Station <?php echo $sid; ?>
        </option>
    <?php endforeach; ?>
</select>

<hr>

<!-- Latest readings -->
<h2>Latest Reading</h2>
<p>pH: <strong id="val_ph">--</strong></p>
<p>Temp: <strong id="val_temp">--</strong> °C</p>
<p>Level: <strong id="val_level">--</strong> %</p>
<p>Status: <strong id="val_status">--</strong></p>
<p>Recorded: <span id="val_time">--</span></p>

<hr>

<!-- Setpoints -->
<h2>Current Setpoints</h2>
<p>Target pH: <strong id="sp_ph">--</strong></p>
<p>Target Temp: <strong id="sp_temp">--</strong> °C</p>
<p>Target Level: <strong id="sp_level">--</strong> %</p>
<p>Last updated: <span id="sp_time">--</span></p>

<hr>

<!-- History table -->
<h2>Last 30 Readings</h2>
<table border="1" cellpadding="6" cellspacing="0" id="historyTable">
    <thead>
        <tr>
            <th>#</th>
            <th>pH</th>
            <th>Temp (°C)</th>
            <th>Level (%)</th>
            <th>Status</th>
            <th>Recorded At</th>
        </tr>
    </thead>
    <tbody id="historyBody">
        <tr><td colspan="6">Loading...</td></tr>
    </tbody>
</table>

<script>
    const stationSelect = document.getElementById('stationSelect');

    function fetchData() {
        const stationId = stationSelect.value;

        fetch(`getdata.php?station_id=${stationId}`)
            .then(r => r.json())
            .then(data => {
                // --- Latest ---
                const l = data.latest;
                if (l) {
                    document.getElementById('val_ph').textContent    = l.ph    ?? '--';
                    document.getElementById('val_temp').textContent  = l.temp  ?? '--';
                    document.getElementById('val_level').textContent = l.level ?? '--';
                    document.getElementById('val_status').textContent = l.status ?? 'unchecked';
                    document.getElementById('val_time').textContent  = l.recorded_at ?? '--';
                }

                // --- Setpoints ---
                const sp = data.setpoints;
                if (sp) {
                    document.getElementById('sp_ph').textContent    = sp.a_ph    ?? '--';
                    document.getElementById('sp_temp').textContent  = sp.a_temp  ?? '--';
                    document.getElementById('sp_level').textContent = sp.a_level ?? '--';
                    document.getElementById('sp_time').textContent  = sp.updated_at ?? '--';
                }

                // --- History ---
                const tbody = document.getElementById('historyBody');
                tbody.innerHTML = '';
                if (data.history && data.history.length > 0) {
                    data.history.forEach((row, i) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${i + 1}</td>
                            <td>${row.ph}</td>
                            <td>${row.temp}</td>
                            <td>${row.level}</td>
                            <td>${row.status ?? 'unchecked'}</td>
                            <td>${row.recorded_at}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6">No data</td></tr>';
                }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    // On station change
    stationSelect.addEventListener('change', () => {
        history.pushState(null, null, `?station_id=${stationSelect.value}`);
        fetchData();
    });

    // Auto refresh every 5s
    setInterval(fetchData, 5000);

    // Initial load
    fetchData();
</script>

</body>
</html>
