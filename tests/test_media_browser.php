<?php
require_once __DIR__ . '/../lib/MediaBrowser.php';
$base = sys_get_temp_dir() . '/efm-test-' . getmypid();
mkdir($base . '/videos/Halloween', 0777, true);
mkdir($base . '/playlists', 0777, true);
file_put_contents($base . '/videos/Halloween/example.mp4', 'video');
file_put_contents($base . '/videos/notes.txt', 'not video');
file_put_contents($base . '/playlists/Test.json', json_encode(array('name'=>'Test','version'=>3,'desc'=>'keep','mainPlaylist'=>array(array('type'=>'pause','duration'=>1)),'playlistInfo'=>array('total_items'=>1))));
$settings = array('mediaDirectory'=>$base, 'videoDirectory'=>$base . '/videos', 'playlistDirectory'=>$base . '/playlists');
function check_true($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
check_true(efm_list_media(efm_video_root($settings), '')[0]['path'] === 'Halloween', 'lists directories first');
check_true(efm_path_inside(efm_video_root($settings), 'Halloween/example.mp4', true) !== false, 'accepts nested video');
check_true(efm_path_inside(efm_video_root($settings), '../playlists/Test.json', true) === false, 'rejects traversal');
check_true(efm_path_inside(efm_video_root($settings), '/etc/passwd', true) === false, 'rejects absolute path');
if (function_exists('symlink') && @symlink($base . '/videos', $base . '/videos/link')) {
    check_true(efm_path_inside(efm_video_root($settings), 'link/Halloween/example.mp4', true) === false, 'rejects symlink components');
    unlink($base . '/videos/link');
}
$result = efm_add_to_playlist($settings, 'Test', array('Halloween/example.mp4'));
check_true($result['ok'] && $result['added'] === 1, 'adds selected video');
$result = efm_add_to_playlist($settings, 'Test', array('Halloween/example.mp4'));
check_true($result['ok'] && $result['added'] === 0 && $result['skipped'] === 1, 'skips duplicate');
$playlist = json_decode(file_get_contents($base . '/playlists/Test.json'), true);
check_true($playlist['desc'] === 'keep' && $playlist['mainPlaylist'][1]['mediaName'] === 'Halloween/example.mp4', 'preserves playlist data and relative path');
check_true(efm_add_to_playlist($settings, 'Test', array('../bad.mp4'))['ok'] === false, 'rejects unsafe add');
unlink($base . '/videos/Halloween/example.mp4'); rmdir($base . '/videos/Halloween'); unlink($base . '/videos/notes.txt'); rmdir($base . '/videos'); unlink($base . '/playlists/Test.json'); rmdir($base . '/playlists'); rmdir($base);
echo "PASS\n";
