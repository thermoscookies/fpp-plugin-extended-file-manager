<?php
/** Duration probing, playlist statistics, and safe repair helpers. */

function efm_duration_is_valid($duration)
{
    return is_numeric($duration) && is_finite((float) $duration) && (float) $duration > 0;
}

function efm_probe_duration($file, $probeBinary = 'ffprobe')
{
    if (!is_string($file) || !is_file($file) || !is_readable($file)) return false;
    $command = escapeshellarg($probeBinary) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file) . ' 2>/dev/null';
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) return false;
    fclose($pipes[0]);
    $output = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || !efm_duration_is_valid($output)) return false;
    return (float) $output;
}

function efm_playlist_storage($settings, $playlistName)
{
    $playlistName = efm_playlist_name($playlistName);
    $media = isset($settings['mediaDirectory']) ? realpath($settings['mediaDirectory']) : false;
    $directory = isset($settings['playlistDirectory']) ? realpath($settings['playlistDirectory']) : false;
    if ($playlistName === false || $media === false || $directory === false || !is_dir($media) || !is_dir($directory) || ($directory !== $media && strpos($directory, $media . DIRECTORY_SEPARATOR) !== 0)) return false;
    $file = $directory . DIRECTORY_SEPARATOR . $playlistName . '.json';
    if (!is_file($file) || is_link($file)) return false;
    return array('name' => $playlistName, 'directory' => $directory, 'file' => $file);
}

function efm_probe_binary($settings)
{
    return isset($settings['ffprobeBinary']) && is_string($settings['ffprobeBinary']) && $settings['ffprobeBinary'] !== '' ? $settings['ffprobeBinary'] : 'ffprobe';
}

function efm_recalculate_playlist_info(&$playlist)
{
    if (!isset($playlist['playlistInfo']) || !is_array($playlist['playlistInfo'])) $playlist['playlistInfo'] = array();
    $totalItems = 0;
    $totalDuration = 0.0;
    foreach (array('leadIn', 'mainPlaylist', 'leadOut') as $section) {
        $entries = isset($playlist[$section]) && is_array($playlist[$section]) ? $playlist[$section] : array();
        $duration = 0.0;
        foreach ($entries as $entry) {
            if (is_array($entry) && efm_duration_is_valid(isset($entry['duration']) ? $entry['duration'] : null)) $duration += (float) $entry['duration'];
        }
        $count = count($entries);
        $playlist['playlistInfo'][$section . '_items'] = $count;
        $playlist['playlistInfo'][$section . '_duration'] = $duration;
        $totalItems += $count;
        $totalDuration += $duration;
    }
    $playlist['playlistInfo']['total_items'] = $totalItems;
    $playlist['playlistInfo']['total_duration'] = $totalDuration;
}

function efm_playlist_info_summary($playlist)
{
    $info = isset($playlist['playlistInfo']) && is_array($playlist['playlistInfo']) ? $playlist['playlistInfo'] : array();
    $summary = array();
    foreach (array('leadIn', 'mainPlaylist', 'leadOut') as $section) {
        $summary[$section . '_items'] = isset($info[$section . '_items']) && is_numeric($info[$section . '_items']) ? (int) $info[$section . '_items'] : 0;
        $summary[$section . '_duration'] = isset($info[$section . '_duration']) && is_numeric($info[$section . '_duration']) ? (float) $info[$section . '_duration'] : 0.0;
    }
    $summary['total_items'] = isset($info['total_items']) && is_numeric($info['total_items']) ? (int) $info['total_items'] : 0;
    $summary['total_duration'] = isset($info['total_duration']) && is_numeric($info['total_duration']) ? (float) $info['total_duration'] : 0.0;
    return $summary;
}

function efm_write_playlist_locked($handle, $directory, $file, $playlist)
{
    $encoded = json_encode($playlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $temporary = $encoded === false ? false : tempnam($directory, '.efm-playlist-');
    $written = $temporary === false ? false : file_put_contents($temporary, $encoded, LOCK_EX);
    if ($encoded === false || $temporary === false || $written !== strlen($encoded) || !rename($temporary, $file)) {
        if ($temporary !== false && is_file($temporary)) unlink($temporary);
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function efm_repair_cache_directory($settings)
{
    $media = isset($settings['mediaDirectory']) ? realpath($settings['mediaDirectory']) : false;
    if ($media === false || !is_dir($media)) return false;
    $parent = $media . DIRECTORY_SEPARATOR . 'plugindata';
    $directory = $parent . DIRECTORY_SEPARATOR . 'fpp-plugin-extended-file-manager';
    $cache = $directory . DIRECTORY_SEPARATOR . 'repair-cache';
    foreach (array($parent, $directory, $cache) as $path) {
        if (file_exists($path) && (!is_dir($path) || is_link($path))) return false;
        if (!file_exists($path) && !mkdir($path, 0770)) return false;
    }
    return $cache;
}

function efm_repair_cache_cleanup($directory, $now)
{
    $files = glob($directory . DIRECTORY_SEPARATOR . '*.json');
    if ($files === false) return;
    foreach ($files as $file) {
        if (is_file($file) && !is_link($file)) {
            $cache = json_decode(file_get_contents($file), true);
            if (!is_array($cache) || !isset($cache['expires']) || (int) $cache['expires'] < $now) unlink($file);
        }
    }
}

function efm_repair_cache_store($settings, $cache)
{
    $directory = efm_repair_cache_directory($settings);
    if ($directory === false) return false;
    $now = time();
    efm_repair_cache_cleanup($directory, $now);
    try { $token = bin2hex(random_bytes(24)); } catch (Exception $exception) { return false; }
    $cache['token'] = $token;
    $cache['created'] = $now;
    $cache['expires'] = $now + 300;
    $file = $directory . DIRECTORY_SEPARATOR . $token . '.json';
    $encoded = json_encode($cache, JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($file, $encoded, LOCK_EX) !== strlen($encoded)) {
        if (is_file($file)) unlink($file);
        return false;
    }
    chmod($file, 0600);
    return array('token' => $token, 'expires' => $cache['expires']);
}

function efm_repair_cache_consume($settings, $token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token)) return false;
    $directory = efm_repair_cache_directory($settings);
    if ($directory === false) return false;
    $file = $directory . DIRECTORY_SEPARATOR . $token . '.json';
    if (!is_file($file) || is_link($file)) return false;
    $encoded = file_get_contents($file);
    unlink($file);
    $cache = json_decode($encoded, true);
    return is_array($cache) && isset($cache['token'], $cache['expires']) && hash_equals($cache['token'], $token) && (int) $cache['expires'] >= time() ? $cache : false;
}

function efm_repair_file_hash($file)
{
    $hash = is_string($file) && is_file($file) && is_readable($file) ? hash_file('sha256', $file) : false;
    return is_string($hash) && $hash !== '' ? $hash : false;
}

function efm_apply_repair_token($settings, $storage, $root, $token)
{
    $cache = efm_repair_cache_consume($settings, $token);
    if ($cache === false || !isset($cache['playlist'], $cache['playlistHash'], $cache['mediaHashes'], $cache['updates']) || $cache['playlist'] !== $storage['name']) return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('The repair preview is missing, expired, or already used.'));
    $currentHash = efm_repair_file_hash($storage['file']);
    if (!is_string($cache['playlistHash']) || $currentHash === false || !hash_equals($cache['playlistHash'], $currentHash)) return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('The playlist changed after the preview. Run the dry-run again.'));
    $handle = fopen($storage['file'], 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('Could not lock playlist.'));
    $playlist = json_decode(stream_get_contents($handle), true);
    if (!is_array($playlist)) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('Playlist JSON is invalid.')); }
    foreach ($cache['updates'] as $update) {
        if (!is_array($update) || !isset($update['section'], $update['index'], $update['path'], $update['proposedDuration']) || !array_key_exists('currentDuration', $update) || !isset($playlist[$update['section']][$update['index']]) || !is_array($playlist[$update['section']][$update['index']])) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('The playlist changed after the preview. Run the dry-run again.')); }
        $entry = $playlist[$update['section']][$update['index']];
        if (!isset($entry['mediaName']) || $entry['mediaName'] !== $update['path'] || (isset($entry['duration']) ? $entry['duration'] !== $update['currentDuration'] : $update['currentDuration'] !== null)) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('The playlist changed after the preview. Run the dry-run again.')); }
        $file = efm_path_inside($root, $update['path'], true);
        if ($file === false || !isset($cache['mediaHashes'][$update['path']]) || !is_string($cache['mediaHashes'][$update['path']]) || efm_repair_file_hash($file) !== $cache['mediaHashes'][$update['path']]) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('A referenced media file changed after the preview. Run the dry-run again.')); }
        $playlist[$update['section']][$update['index']]['duration'] = $update['proposedDuration'];
    }
    $before = efm_playlist_info_summary($playlist);
    efm_recalculate_playlist_info($playlist);
    $after = efm_playlist_info_summary($playlist);
    if (!efm_write_playlist_locked($handle, $storage['directory'], $storage['file'], $playlist)) return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('Could not safely write playlist.'));
    return array('ok' => true, 'applied' => true, 'playlist' => $storage['name'], 'updates' => $cache['updates'], 'errors' => array(), 'before' => $before, 'after' => $after);
}

function efm_add_to_playlist($settings, $playlistName, $paths)
{
    $root = efm_video_root($settings);
    $storage = efm_playlist_storage($settings, $playlistName);
    if ($root === false || $storage === false || !is_array($paths) || count($paths) < 1 || count($paths) > 500) return array('ok' => false, 'error' => 'Invalid playlist or media selection.');
    $valid = array();
    foreach ($paths as $path) {
        $path = efm_relative_path($path);
        if ($path === false || efm_path_inside($root, $path, true) === false || !efm_video_extension($path)) return array('ok' => false, 'error' => 'One or more selected files are not safe video files.');
        if (!in_array($path, $valid, true)) $valid[] = $path;
    }
    $durations = array();
    foreach ($valid as $path) {
        $duration = efm_probe_duration(efm_path_inside($root, $path, true), efm_probe_binary($settings));
        if ($duration === false) return array('ok' => false, 'error' => 'Could not read the duration of ' . $path . '. No playlist changes were made.');
        $durations[$path] = $duration;
    }
    $handle = fopen($storage['file'], 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return array('ok' => false, 'error' => 'Could not lock playlist.');
    $playlist = json_decode(stream_get_contents($handle), true);
    if (!is_array($playlist)) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'error' => 'Playlist JSON is invalid.'); }
    if (!isset($playlist['mainPlaylist']) || !is_array($playlist['mainPlaylist'])) $playlist['mainPlaylist'] = array();
    $existing = array();
    foreach ($playlist['mainPlaylist'] as $entry) if (is_array($entry) && isset($entry['mediaName'])) $existing[$entry['mediaName']] = true;
    $added = 0;
    foreach ($valid as $path) {
        if (isset($existing[$path])) continue;
        $playlist['mainPlaylist'][] = array('type' => 'media', 'enabled' => 1, 'playOnce' => 0, 'mediaName' => $path, 'duration' => $durations[$path]);
        $existing[$path] = true;
        $added++;
    }
    $playlist['name'] = isset($playlist['name']) ? $playlist['name'] : $storage['name'];
    $playlist['version'] = isset($playlist['version']) ? $playlist['version'] : 3;
    efm_recalculate_playlist_info($playlist);
    if (!efm_write_playlist_locked($handle, $storage['directory'], $storage['file'], $playlist)) return array('ok' => false, 'error' => 'Could not safely write playlist.');
    return array('ok' => true, 'added' => $added, 'skipped' => count($valid) - $added, 'playlist' => $storage['name']);
}

function efm_repair_playlist_durations($settings, $playlistName, $apply = false, $repairToken = '')
{
    $root = efm_video_root($settings);
    $storage = efm_playlist_storage($settings, $playlistName);
    if ($root === false || $storage === false) return array('ok' => false, 'error' => 'Playlist or media storage is unavailable.');
    if ($apply) return efm_apply_repair_token($settings, $storage, $root, $repairToken);
    $playlistHash = efm_repair_file_hash($storage['file']);
    if ($playlistHash === false) return array('ok' => false, 'applied' => false, 'playlist' => $storage['name'], 'errors' => array('Could not verify the playlist safely.'));
    $handle = fopen($storage['file'], 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return array('ok' => false, 'error' => 'Could not lock playlist.');
    $playlist = json_decode(stream_get_contents($handle), true);
    if (!is_array($playlist)) { flock($handle, LOCK_UN); fclose($handle); return array('ok' => false, 'error' => 'Playlist JSON is invalid.'); }
    $before = efm_playlist_info_summary($playlist);
    $proposed = $playlist;
    $updates = array();
    $mediaHashes = array();
    $errors = array();
    foreach (array('leadIn', 'mainPlaylist', 'leadOut') as $section) {
        if (!isset($playlist[$section]) || !is_array($playlist[$section])) continue;
        foreach ($playlist[$section] as $index => $entry) {
            if (!is_array($entry) || !isset($entry['mediaName']) || efm_duration_is_valid(isset($entry['duration']) ? $entry['duration'] : null)) continue;
            $path = efm_relative_path($entry['mediaName']);
            $file = $path !== false && efm_video_extension($path) ? efm_path_inside($root, $path, true) : false;
            if ($file === false) { $errors[] = $section . '[' . $index . '] ' . (isset($entry['mediaName']) ? $entry['mediaName'] : 'unknown media'); continue; }
            $duration = efm_probe_duration($file, efm_probe_binary($settings));
            if ($duration === false) { $errors[] = $section . '[' . $index . '] ' . $path; continue; }
            $mediaHash = efm_repair_file_hash($file);
            if ($mediaHash === false) { $errors[] = $section . '[' . $index . '] ' . $path; continue; }
            $mediaHashes[$path] = $mediaHash;
            $updates[] = array('section' => $section, 'index' => $index, 'path' => $path, 'currentDuration' => isset($entry['duration']) ? $entry['duration'] : null, 'proposedDuration' => $duration);
            $proposed[$section][$index]['duration'] = $duration;
        }
    }
    $afterPlaylist = $proposed;
    efm_recalculate_playlist_info($afterPlaylist);
    $result = array('ok' => count($errors) === 0, 'applied' => false, 'playlist' => $storage['name'], 'updates' => $updates, 'errors' => $errors, 'before' => $before, 'after' => efm_playlist_info_summary($afterPlaylist));
    if (count($errors) > 0 || count($updates) === 0) { flock($handle, LOCK_UN); fclose($handle); return $result; }
    $stored = efm_repair_cache_store($settings, array('playlist' => $storage['name'], 'playlistHash' => $playlistHash, 'mediaHashes' => $mediaHashes, 'updates' => $updates));
    if ($stored === false) { flock($handle, LOCK_UN); fclose($handle); return array_merge($result, array('ok' => false, 'errors' => array('Could not store the repair preview safely.'))); }
    $result['repairToken'] = $stored['token'];
    $result['repairTokenExpires'] = $stored['expires'];
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result;
}
