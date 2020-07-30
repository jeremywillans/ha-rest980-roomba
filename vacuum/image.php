<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

// ADJUST THESE PARAMETERS
$vacuum_log = 'http://<ip or fqdn of docker host>:<nginxphpport>/vacuum.log'; # Could also be HTTPS
$set_first_coordinate = 0; # Ability to skip initial coordinate(s) if incorrect data logged
$overlay_image = 'floor.png'; # Background Layer
$overlay_walls = false; # Allows overlaying of walls, used in fill mode to cover 'spray'
$walls_image = 'walls.png'; # Walls Image must contain transparent floor
$show_stuck_positions = true; 
$line_thickness = 2; # Default 2, Set to ~60 for Fill Mode
$map_width = 1050; # Ensure overlay and wall images match this size
$map_height = 900; # Ensure overlay and wall images match this size
$x_offset = 220;
$y_offset = 220;
$flip_vertical = false;
$flip_horizontal = false;
$rotate_angle = 0; # Allows rotating of the roomba lines
$scale=1.00; # Allows scaling of roomba lines
$ha_rest980 = 'https://<ip or fqdn of home assistant>:<haport>/api/states/sensor.rest980';
$ha_token = '<ha_long_live_token>';
$ha_timezone = 'Australia/Brisbane'; # Supported Timezones https://www.php.net/manual/en/timezones.php
$ha_text_delimiter = " \n"; # How text is displayed on the map top " \n" --> New Line ## " |" --> Show on one line
//
// Line Color - RGB
// -1 represents gradual increase from 0 to 255 based on number of logged locations
//
$color_red = -1;
$color_green = 255;
$color_blue = -1;
//
// Examples
// red = -1 , green = 255 , blue = -1  ---> Green to White Fade
// red = 0 , green = -1 , blue = 255   ---> Blue to Aqua Fade
// red = 0 , green = 0 , blue = 255    ---> Solid Blue
//
///////////////////////////////////////////////////////////////////

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

$roomba = imagecreatefrompng('roomba.png');
imagealphablending($roomba, false);
imagesavealpha($roomba, true);

foreach($coords as $i => $coord) {
  # Skip initial coordinates if needed
  if ($i < $set_first_coordinate) {
    continue;
  }
  $split = explode(",", $coord);
  if(sizeof($split)<2) {
    if(($coord == "Stuck") & ($show_stuck_positions)) {
      $roomba_stuck = imagecreatefrompng('roomba_stuck.png');
      imagealphablending($roomba_stuck, false);
      imagesavealpha($roomba_stuck, true);
      $roomba_stuck = imagerotate($roomba_stuck, $oldtheta*-1, imageColorAllocateAlpha($roomba_stuck, 0, 0, 0, 127));
      imagealphablending($roomba_stuck, false);
      imagesavealpha($roomba_stuck, true);
      imagecopy($image, $roomba_stuck, $oldx-10, $oldy-5, 0, 0, imagesx($roomba_stuck), imagesy($roomba_stuck));
      imagedestroy($roomba_stuck);
    }
    continue;
  }
  
  $part= hexdec("ff");
  $part = round($part * $i/sizeof($coords));

  // Calculate Line Color
  $red = ($color_red === -1 ? $part : $color_red);
  $green = ($color_green === -1 ? $part : $color_green);
  $blue = ($color_blue === -1 ? $part : $color_blue);
  
  $color = imagecolorallocate($image, $red, $green, $blue);
  $tmpx = $split[1]+$x_offset;
  $tmpy = $split[0]+$y_offset;
  $theta = $split[2];
  
  // Rotate Calculations
  $x=($tmpx*cos(deg2rad($rotate_angle))+$tmpy*sin(deg2rad($rotate_angle)))*$scale;
  $y=(-1*$tmpx*sin(deg2rad($rotate_angle))+$tmpy*cos(deg2rad($rotate_angle)))*$scale;
  
  $boxsize=4;
  $shift_y = 2;
  $shift_x = -2;
  
  imagerectangle($image, $x+$shift_x, $y+$shift_y, $x+$boxsize+$shift_x, $y+$boxsize+$shift_y, $color);
  if(isset($oldx) && isset($oldy)) {
    imagelinethick($image, $oldx+($boxsize/2)+$shift_x, $oldy+($boxsize/2)+$shift_y, $x+($boxsize/2)+$shift_x, $y+($boxsize/2)+$shift_y, $color, $line_thickness);
  }
  
  if($i+1==sizeof($coords)) {
    if (sizeof($split)>2) {
      $roomba = imagerotate($roomba, $theta*-1, imageColorAllocateAlpha($roomba, 0, 0, 0, 127));
      imagealphablending($roomba, false);
      imagesavealpha($roomba, true);
      imagecopy($image, $roomba, $x-10, $y-5, 0, 0, imagesx($roomba), imagesy($roomba));
    }
  }
  
  $oldx = $x;
  $oldy = $y;
  $oldtheta = $theta;
}

if(in_array($lastline, $end)) {
  imagedestroy($roomba);
  
  if($lastline == "Stuck") {
    $overlayImage = imagecreatefrompng('roomba_stuck.png');
    imagealphablending($overlayImage, false);
    imagesavealpha($overlayImage, true);
    $color = imagecolorallocate($image, 0, 149, 223);
    $finishedRoomba = imagerotate($overlayImage, $theta*-1, imageColorAllocateAlpha($overlayImage, 0, 0, 0, 127));
    imagelinethick($image, $oldx+($boxsize/2), $oldy+($boxsize/2), $x+($boxsize/2)+3, $y+($boxsize/2)+10, $color, 2);
    imagecopy($image, $finishedRoomba, $oldx-10, $oldy-5, 0, 0, imagesx($finishedRoomba), imagesy($finishedRoomba));
  }
  else if($lastline == "Finished") {
    $overlayImage = imagecreatefrompng('roomba_charging.png');
    imagealphablending($overlayImage, false);
    imagesavealpha($overlayImage, true);
    $color = imagecolorallocate($image, 0, 149, 223);
    $finishedRoomba = imagerotate($overlayImage, $theta*-1, imageColorAllocateAlpha($overlayImage, 0, 0, 0, 127));
    imagelinethick($image, $oldx+($boxsize/2), $oldy+($boxsize/2), $x+($boxsize/2)+3, $y+($boxsize/2)+10, $color, 2);
    imagecopy($image, $finishedRoomba, $oldx-10, $oldy-5, 0, 0, imagesx($finishedRoomba), imagesy($finishedRoomba));
  }
  
}
if($flip_vertical) {
  imageflip( $image, IMG_FLIP_VERTICAL );
}

if($flip_horizontal) {
  imageflip( $image, IMG_FLIP_HORIZONTAL );
}

// Create Final Image
$dest = imagecreatetruecolor($map_width,$map_height);
imagesavealpha($dest, true);
// Create Background Image
$overlayImage = imagecreatefrompng($overlay_image);
// Merge Background Image
imagecopy($dest, $overlayImage, 0, 0, 0, 0, imagesx($overlayImage), imagesy($overlayImage));
imagedestroy($overlayImage);
// Merge Roomba Lines
imagecopy($dest, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

if ($overlay_walls) {
  // Create Walls Image
  $overlayWalls = imagecreatefrompng($walls_image);
  // Merge Walls Image
  imagecopy($dest, $overlayWalls, 0, 0, 0, 0, imagesx($overlayWalls), imagesy($overlayWalls));
  imagedestroy($overlayWalls);
}

$string = "";

if($lastline == "Finished") {
  $finished=true;
  $status="Finished";
  
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
  $data = json_decode($server_output);
  $battery_level = $data->attributes->batPct;
  $string.=$ha_text_delimiter." Battery: ".$battery_level."%";
  
}
else if($lastline == "Stuck"){
  $finished=false;
  $status="Stuck";
}
else {
  $finished=false;
  $status="Running";
}

date_default_timezone_set($ha_timezone);
$dt = date('H:i:s Y-m-d', $date);
$txt = " Started: ".$dt.$ha_text_delimiter." Status: ".$status.$string;
$white = imagecolorallocate($dest, 255, 255, 255);
$font = "./monaco.ttf"; 
imagettftext($dest, 10, 0, 5, 15, $white, $font, $txt);

header("Content-Type: image/png");
imagepng($dest);
if(isset($_GET['last'])) {
  imagepng($dest, "latest.png");
  imagepng($dest, $date.".png");
}
imagedestroy($dest);
imagedestroy($roomba);
imagedestroy($roomba_stuck);
imagedestroy($overlayImage);
exit;