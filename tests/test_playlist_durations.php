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
$probeCount = $base . '/probe-count';
file_put_contents($probe, "#!/bin/sh\necho 1 >> \"$probeCount\"\nfile=\"\"\nfor arg do file=\"\$arg\"; done\ncase \"\$file\" in\n*existing.mp4) echo 10;;\n*new.mp4) echo 169.202375;;\n*repair.mp4) echo 42.5;;\n*broken.mp4) exit 1;;\n*) echo 1;;\nesac\n");
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
duration_check($preview['updates'][0]['currentDuration'] === null && $preview['updates'][0]['proposedDuration'] === 42.5, 'repair reports current and proposed duration');
duration_check($preview['before']['total_duration'] === 0.0 && $preview['after']['total_duration'] === 52.5, 'repair reports current and proposed totals');
$dryRun = file_get_contents($base . '/playlists/Repair.json');
$dryDecoded = json_decode($dryRun, true);
duration_check(!isset($dryDecoded['mainPlaylist'][0]['duration']), 'repair dry-run leaves playlist unchanged');
$durationProbeCount = (int) trim(file_get_contents($probeCount));
$applied = efm_repair_playlist_durations($settings, 'Repair', true, $preview['repairToken']);
$durationProbeCountAfterApply = (int) trim(file_get_contents($probeCount));
duration_check($durationProbeCountAfterApply === $durationProbeCount, 'repair confirmation uses cached probe results');
$repaired = json_decode(file_get_contents($base . '/playlists/Repair.json'), true);
duration_check($applied['ok'] && $applied['applied'] && $applied['after']['total_duration'] === 52.5 && $repaired['mainPlaylist'][0]['duration'] === 42.5 && $repaired['mainPlaylist'][1]['label'] === 'keep', 'repair applies only missing duration and preserves metadata');
$secondUse = efm_repair_playlist_durations($settings, 'Repair', true, $preview['repairToken']);
duration_check(!$secondUse['ok'] && !$secondUse['applied'], 'repair token is single-use');

$mutatedPlaylist = array('name'=>'Mutated','mainPlaylist'=>array(array('type'=>'media','mediaName'=>'Easter/repair.mp4')), 'playlistInfo'=>array('total_items'=>1));
file_put_contents($base . '/playlists/Mutated.json', json_encode($mutatedPlaylist));
$mutatedPreview = efm_repair_playlist_durations($settings, 'Mutated', false);
file_put_contents($base . '/videos/Easter/repair.mp4', 'changed');
$mutatedApply = efm_repair_playlist_durations($settings, 'Mutated', true, $mutatedPreview['repairToken']);
$mutatedSaved = json_decode(file_get_contents($base . '/playlists/Mutated.json'), true);
duration_check($mutatedApply['ok'] && $mutatedApply['applied'] && $mutatedSaved['mainPlaylist'][0]['duration'] === 42.5, 'confirmation does not reread or revalidate media contents');

$brokenRepair = array('name'=>'Broken','mainPlaylist'=>array(array('type'=>'media','mediaName'=>'Easter/broken.mp4')), 'playlistInfo'=>array('total_items'=>1));
file_put_contents($base . '/playlists/Broken.json', json_encode($brokenRepair));
$brokenBefore = file_get_contents($base . '/playlists/Broken.json');
$failedRepair = efm_repair_playlist_durations($settings, 'Broken', true);
duration_check(!$failedRepair['ok'] && !$failedRepair['applied'] && count($failedRepair['errors']) === 1 && file_get_contents($base . '/playlists/Broken.json') === $brokenBefore, 'failed repair probe makes no changes');

function duration_remove_tree($path) { if (!is_dir($path)) { if (is_file($path)) unlink($path); return; } foreach (scandir($path) as $item) if ($item !== '.' && $item !== '..') duration_remove_tree($path . DIRECTORY_SEPARATOR . $item); rmdir($path); }
duration_remove_tree($base);
echo "PASS\n";
