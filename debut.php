<?php 

$statsFile = 'stats.html';
$ip = $_SERVER['REMOTE_ADDR'];
$dateTime = date('Y-m-d H:i:s');
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';

$papage = $_SERVER['REQUEST_URI'];


$newEntry = "<tr><td>$ip</td><td>$dateTime</td><td>$referer depuis $papage</td></tr>";
$initialContent = "<html><head>
<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
<script src='https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'></script>
<link rel='stylesheet' href='https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css'>
</head><body>
<table id='statsTable' class='display'>
  <thead>
    <tr><th>IP</th><th>Date et Heure</th><th>Referer</th></tr>
  </thead>
  <tbody></tbody>
</table>
<script>
$(document).ready(function() { $('#statsTable').DataTable(); });
</script>
</body><!-- nouveau --></html>";
$fp = fopen($statsFile, 'c+');
if ($fp === false) {
    die("Erreur lors de l'ouverture de $statsFile.");
}
if (!flock($fp, LOCK_EX)) {
    die("Impossible de verrouiller $statsFile.");
}
$currentContent = stream_get_contents($fp);
if (trim($currentContent) === '') {
    $currentContent = $initialContent;
}
if (strpos($currentContent, '</tbody>') !== false) {
    $currentContent = str_replace('</tbody>', $newEntry . '</tbody>', $currentContent);
} else {
    $currentContent = str_replace('<tbody>', '<tbody>' . $newEntry, $currentContent);
}
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, $currentContent);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
?>