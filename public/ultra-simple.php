<?php
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', 'off');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

for ($i = 1; $i <= 10; $i++) {
    echo "Line {$i}<br>\n";
    flush();
    sleep(1);
}
