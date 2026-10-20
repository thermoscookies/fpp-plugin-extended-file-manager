<?php
require_once __DIR__ . '/lib/MediaBrowser.php';

function getEndpointsfpppluginextendedfilemanager() { return array(array('method'=>'GET','endpoint'=>'media','callback'=>'efmMedia'),array('method'=>'POST','endpoint'=>'add','callback'=>'efmAdd')); }
function efmMedia() { global $settings; $path=isset($_GET['path'])?$_GET['path']:''; $items=efm_list_media(efm_video_root($settings),$path); if($items===false){http_response_code(400);return json(array('error'=>'Invalid media folder.'));} return json(array('path'=>$path,'items'=>$items)); }
function efmAdd() { global $settings; $body=isset($GLOBALS['_POST'])?$GLOBALS['_POST']:array(); if(!is_array($body)||count($body)===0) $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body)) $body=array(); $result=efm_add_to_playlist($settings,isset($body['playlist'])?$body['playlist']:'',isset($body['paths'])?$body['paths']:array()); if(!$result['ok']) http_response_code(400); return json($result); }
