<?php
require_once __DIR__ . '/lib/MediaBrowser.php';

function getEndpointsfpppluginextendedfilemanager() { return array(array('method'=>'GET','endpoint'=>'media','callback'=>'efmMedia'),array('method'=>'POST','endpoint'=>'add','callback'=>'efmAdd'),array('method'=>'POST','endpoint'=>'repair','callback'=>'efmRepair')); }
function efmMedia() { global $settings; $path=isset($_GET['path'])?$_GET['path']:''; $items=efm_list_media(efm_video_root($settings),$path); if($items===false){http_response_code(400);return json(array('error'=>'Invalid media folder.'));} return json(array('path'=>$path,'items'=>$items)); }
function efmRequestBody() { $body=isset($GLOBALS['_POST'])?$GLOBALS['_POST']:array(); if(!is_array($body)||count($body)===0) $body=json_decode(file_get_contents('php://input'),true); return is_array($body)?$body:array(); }
function efmAdd() { global $settings; $body=efmRequestBody(); $result=efm_add_to_playlist($settings,isset($body['playlist'])?$body['playlist']:'',isset($body['paths'])?$body['paths']:array()); if(!$result['ok']) http_response_code(400); return json($result); }
function efmRepair() { global $settings; $body=efmRequestBody(); $result=efm_repair_playlist_durations($settings,isset($body['playlist'])?$body['playlist']:'',isset($body['apply'])&&($body['apply']===true||$body['apply']==='true'||$body['apply']==='1')); if(!$result['ok']) http_response_code(400); return json($result); }
