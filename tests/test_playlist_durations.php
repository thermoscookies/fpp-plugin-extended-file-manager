<?php
require_once __DIR__ . '/../lib/MediaBrowser.php';
$base = sys_get_temp_dir() . '/efm-duration-test-' . getmypid();
mkdir($base . '/videos/Christmas', 0777, true);
mkdir($base . '/videos/Easter', 0777, true);
mkdir($base . '/playlists', 0777, true);
file_put_contents($base . '/videos/Christmas/existing.mp4', 'video');
file_put_contents($base . '/videos/Christmas/new.mp4', 'video');
file_put_contents($base . '/videos/Easter/repair.mp4', 'video');
file_put_contents($base . '/videos/Easter/broken.mp4', 'video');
$probe = $base . '/ffprobe';
file_put_contents($probe, "#!/bin/sh\nfile=\"\"\nfor arg do file=\"\$arg\"; done\ncase \"\$file\" in\n*existing.mp4) echo 10;;\n*new.mp4) echo 169.202375;;\n*repair.mp4) echo 42.5;;\n*broken.mp4) exit 1;;\n*) echo 1;;\nesac\n");
chmod($probe, 0755);
$settings = array('mediaDirectory'=>$base, 'videoDirectory'=>$base . '/videos', 'playlistDirectory'=>$base . '/playlists', 'ffprobeBinary'=>$probe);
function duration_check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

$playlist = array('name'=>'Christmas','version'=>3,'desc'=>'preserve','leadIn'=>array(array('type'=>'pause','duration'=>2)), 'mainPlaylist'=>array(array('type'=>'media','mediaName'=>'Christmas/existing.mp4','duration'=>10,'custom'=>'keep')), 'leadOut'=>array(array('type'=>'pause','duration'=>3)), 'playlistInfo'=>array('total_items'=>99,'total_duration'=>99));
file_put_contents($base . '/playlists/Christmas.json', json_encode($playlist));
$result = efm_add_to_playlist($settings, 'Christmas', array('Christmas/new.mp4'));
duration_check($result['ok'] && $result['added'] === 1, 'adds subfolder video with probe duration');
$saved = json_decode(file_get_contents($base . '/playlists/Christmas.json'), true);
duration_check($saved['desc'] === 'preserve' && $saved['mainPlaylist'][0]['custom'] === 'keep' && $saved['mainPlaylist'][1]['duration'] === 169.202375, 'preserves mixed existing/new entry metadata');
duration_check($saved['playlistInfo']['leadIn_items'] === 1 && $saved['playlistInfo']['mainPlaylist_items'] === 2 && $saved['playlistInfo']['leadOut_items'] === 1, 'recalculates section item counts');
duration_check($saved['playlistInfo']['mainPlaylist_duration'] === 179.202375 && $saved['playlistInfo']['total_duration'] === 184.202375 && $saved['playlistInfo']['total_items'] === 4, 'recalculates section and total durations');
$result = efm_add_to_playlist($settings, 'Christmas', array('Christmas/new.mp4'));
duration_check($result['ok'] && $result['added'] === 0 && $result['skipped'] === 1, 'prevents duplicate entries');

$before = file_get_contents($base . '/playlists/Christmas.json');
$result = efm_add_to_playlist($settings, 'Christmas', array('Easter/broken.mp4'));
duration_check(!$result['ok'] && file_get_contents($base . '/playlists/Christmas.json') === $before, 'failed probe makes no playlist changes');

$repairPlaylist = array('name'=>'Repair','mainPlaylist'=>array(array('type'=>'media','mediaName'=>'Easter/repair.mp4'),array('type'=>'media','mediaName'=>'Christmas/existing.mp4','duration'=>10,'label'=>'keep')), 'playlistInfo'=>array('total_items'=>0,'total_duration'=>0));
file_put_contents($base . '/playlists/Repair.json', json_encode($repairPlaylist));
$preview = efm_repair_playlist_durations($settings, 'Repair', false);
duration_check($preview['ok'] && !$preview['applied'] && count($preview['updates']) === 1, 'repair dry-run reports missing duration without writing');
$dryRun = file_get_contents($base . '/playlists/Repair.json');
$dryDecoded = json_decode($dryRun, true);
duration_check(!isset($dryDecoded['mainPlaylist'][0]['duration']), 'repair dry-run leaves playlist unchanged');
$applied = efm_repair_playlist_durations($settings, 'Repair', true);
$repaired = json_decode(file_get_contents($base . '/playlists/Repair.json'), true);
duration_check($applied['ok'] && $applied['applied'] && $repaired['mainPlaylist'][0]['duration'] === 42.5 && $repaired['mainPlaylist'][1]['label'] === 'keep', 'repair applies only missing duration and preserves metadata');

unlink($base . '/playlists/Christmas.json'); unlink($base . '/playlists/Repair.json'); unlink($base . '/videos/Christmas/existing.mp4'); unlink($base . '/videos/Christmas/new.mp4'); unlink($base . '/videos/Easter/repair.mp4'); unlink($base . '/videos/Easter/broken.mp4'); unlink($probe); rmdir($base . '/videos/Christmas'); rmdir($base . '/videos/Easter'); rmdir($base . '/videos'); rmdir($base . '/playlists'); rmdir($base);
echo "PASS\n";
