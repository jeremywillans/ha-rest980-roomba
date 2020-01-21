<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

// ADJUST THESE PARAMETERS
$vacuum_log = 'https://<ip or fqdn of docker host>:<nginxphpport>/vacuum.log';
$overlay_image = 'floor.png';
$map_width = 1050;
$map_height = 900;
$x_offset = 220;
$y_offset = 220;
$flip_vertical = false;
$flip_horizontal = false;
$ha_rest980 = 'https://<ip or fqdn of home assistant>:<haport>/api/states/sensor.rest980';
$ha_token = '<ha_long_live_token>';
$ha_timezone = 'Australia/Brisbane'; # Supported Timezones https://www.php.net/manual/en/timezones.php
/////////////////

if(isset($_GET['clear'])) {
  @unlink("latest.png");
  die();  
}
if(is_file("latest.png")&&!isset($_GET['last'])) {
  header("Content-Type: image/png");
  echo file_get_contents("latest.png");
  die();
}

$coords = file_get_contents($vacuum_log."?v=".time());
$coords = str_replace("(", "", $coords);
$coords = str_replace(")", "", $coords);
$coords = explode("\n", $coords);

$date = strtotime(substr($coords[0], 42));

$lastline = $coords[sizeof($coords)-2];
$end = ["Stuck", "Finished"]; // PAUSE also available

array_shift($coords);
array_shift($coords);
array_pop($coords);

function imagelinethick($image, $x1, $y1, $x2, $y2, $color, $thick = 1)
{
    if ($thick == 1) {
        return imageline($image, $x1, $y1, $x2, $y2, $color);
    }
    $t = $thick / 2 - 0.5;
    if ($x1 == $x2 || $y1 == $y2) {
        return imagefilledrectangle($image, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $color);
    }
    $k = ($y2 - $y1) / ($x2 - $x1);
    $a = $t / sqrt(1 + pow($k, 2));
    $points = array(
        round($x1 - (1+$k)*$a), round($y1 + (1-$k)*$a),
        round($x1 - (1-$k)*$a), round($y1 - (1+$k)*$a),
        round($x2 + (1+$k)*$a), round($y2 - (1-$k)*$a),
        round($x2 + (1-$k)*$a), round($y2 + (1+$k)*$a),
    );
    imagefilledpolygon($image, $points, 4, $color);
    return imagepolygon($image, $points, 4, $color);
}

$image = imagecreatetruecolor($map_width,$map_height);
imagesavealpha($image, true);
$black = imagecolorallocatealpha($image,0,0,0, 127);
imagefill($image,0,0,$black);

$overlayImage = imagecreatefrompng($overlay_image);
imagecopy($image, $overlayImage, 0, 0, 0, 0, imagesx($overlayImage), imagesy($overlayImage));
imagedestroy($overlayImage);

$roomba = imagecreatefrompng('roomba.png');
imagealphablending($roomba, false);
imagesavealpha($roomba, true);

foreach($coords as $i => $coord) {
  $split = explode(",", $coord);
  if(sizeof($split)<2) {
    if($coord == "Stuck") {
      $roomba_stuck = imagecreatefrompng('roomba_stuck.png');
      imagealphablending($roomba_stuck, false);
      imagesavealpha($roomba_stuck, true);
      $roomba_stuck = imagerotate($roomba_stuck, $split[2]*-1, imageColorAllocateAlpha($roomba_stuck, 0, 0, 0, 127));
      imagealphablending($roomba_stuck, false);
      imagesavealpha($roomba_stuck, true);
      imagecopy($image, $roomba_stuck, $oldx-10, $oldy-5, 0, 0, imagesx($roomba_stuck), imagesy($roomba_stuck));
      imagedestroy($roomba_stuck);
    }
    continue;
  }
  
  $part= hexdec("ff");
  $part = round($part * $i/sizeof($coords));
          
  $color = imagecolorallocate($image, $part, 255, $part);
  $x = $split[1]+$x_offset; #($split[1]+120)/2;
  $y = $split[0]+$y_offset; #($split[0]*-1+342)/2;
  
  $boxsize=4;
  $shift_y = 2;
  $shift_x = -2;
  
  imagerectangle($image, $x+$shift_x, $y+$shift_y, $x+$boxsize+$shift_x, $y+$boxsize+$shift_y, $color);
  if(isset($oldx) && isset($oldy)) {
    imagelinethick($image, $oldx+($boxsize/2)+$shift_x, $oldy+($boxsize/2)+$shift_y, $x+($boxsize/2)+$shift_x, $y+($boxsize/2)+$shift_y, $color, 2);
  }
  
  if($i+1==sizeof($coords)) {
    if($coords[$i+1]=="Stuck") {
      
    }
    else {
      $roomba = imagerotate($roomba, $split[2]*-1, imageColorAllocateAlpha($roomba, 0, 0, 0, 127));
      imagealphablending($roomba, false);
      imagesavealpha($roomba, true);
      imagecopy($image, $roomba, $x-10, $y-5, 0, 0, imagesx($roomba), imagesy($roomba));
    }
  }
  
  $oldx = $x;
  $oldy = $y;
}

if(in_array($lastline, $end)) {
  imagedestroy($roomba);
  
  if($lastline == "Stuck") {
    $overlayImage = imagecreatefrompng('roomba_stuck.png');
    imagealphablending($overlayImage, false);
    imagesavealpha($overlayImage, true);
    $color = imagecolorallocate($image, 0, 149, 223);
    $finishedRoomba = imagerotate($overlayImage, 180, imageColorAllocateAlpha($overlayImage, 0, 0, 0, 127));
    imagelinethick($image, $oldx+($boxsize/2), $oldy+($boxsize/2), $x+($boxsize/2)+3, $y+($boxsize/2)+10, $color, 2);
    imagecopy($image, $overlayImage, $oldx-10, $oldy-5, 0, 0, imagesx($overlayImage), imagesy($overlayImage));
  }
  else if($lastline == "Finished") {
    $overlayImage = imagecreatefrompng('roomba_charging.png');
    imagealphablending($overlayImage, false);
    imagesavealpha($overlayImage, true);
    $x; # = (0+120)/2;
    $y; # = (0*-1+330)/2;
    $color = imagecolorallocate($image, 0, 149, 223);
    $finishedRoomba = imagerotate($overlayImage, 180, imageColorAllocateAlpha($overlayImage, 0, 0, 0, 127));
    imagelinethick($image, $oldx+($boxsize/2), $oldy+($boxsize/2), $x+($boxsize/2)+3, $y+($boxsize/2)+10, $color, 2);
    imagecopy($image, $finishedRoomba, $x-5, $y+5, 0, 0, imagesx($overlayImage), imagesy($overlayImage));
  }
  
}
if($flip_vertical) {
  imageflip( $image, IMG_FLIP_VERTICAL );
}

if($flip_horizontal) {
  imageflip( $image, IMG_FLIP_HORIZONTAL );
}

if($lastline == "Finished") {
  $finished=true;
  $status="finished";
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $ha_rest980);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $headers = [
      'Authorization: Bearer '.$ha_token,
      'Content-Type: application/json'
  ];
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $server_output = curl_exec ($ch);
  curl_close ($ch);
  $string = "";
  $data = json_decode($server_output);
  $battery_level = $data->attributes->batPct;
  $string.="\nBattery: ".$battery_level."%";
  
}
else if($lastline == "Stuck"){
  $finished=false;
  $status="stuck";
}
else {
  $finished=false;
  $status="running";
}

date_default_timezone_set($ha_timezone);
$dt = date('H:i:s Y-m-d', $date);
$txt = "\nStarted: ".$dt."\n"."Status: ".$status.$string;
$white = imagecolorallocate($image, 255, 255, 255);
$font = "./monaco.ttf"; 
imagettftext($image, 10, 0, 5, 15, $white, $font, $txt);

header("Content-Type: image/png");
imagepng($image);
if(isset($_GET['last'])) {
  imagepng($image, "latest.png");
  imagepng($image, $date.".png");
}
imagedestroy($image);
imagedestroy($roomba);
imagedestroy($roomba_stuck);
imagedestroy($overlayImage);
exit;
