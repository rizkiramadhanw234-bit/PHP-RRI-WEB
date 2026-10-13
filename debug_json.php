<?php
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'time' => time()]);
exit;
