<?php

function getDistanceBetweenPoints($latitude1, $longitude1, $latitude2, $longitude2, $unit = 'miles') {
  // https://fr.martech.zone/calculate-great-circle-distance/
  $theta = $longitude1 - $longitude2;
  $distance = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta)));
  $distance = acos($distance);
  $distance = rad2deg($distance);
  $distance = $distance * 60 * 1.1515;
  switch ($unit) {
    case 'miles':
      break;
    case 'kilometers':
      $distance = $distance * 1.609344;
  }
  return (round($distance, 2));
}

function getAzimuth($_latitude1, $_longitude1, $_latitude2, $_longitude2) {

  /*
  $x = cos($_latitude1) * sin($_latitude2) - sin($_latitude1) * cos($_latitude2) * cos($_longitude2 - $_longitude1);
  $y = sin($_longitude2 - $_longitude1) * cos($_latitude2);
  $Azimuth = 2 * atan($y / (sqrt($x ** 2 + $y ** 2) + $x));
  */
  $theta = $_longitude2 - $_longitude1;
  $x = cos(deg2rad($_latitude1)) * sin(deg2rad($_latitude2)) - sin(deg2rad($_latitude1)) * cos(deg2rad($_latitude2)) * cos(deg2rad($theta));
  $y = sin(deg2rad($theta)) * cos(deg2rad($_latitude2));
  $Azimuth = 2 * atan($y / (sqrt($x ** 2 + $y ** 2) + $x));
  return round(rad2deg($Azimuth), 0);
}

function getUTCoffset($City) {
  $dtz = new DateTimeZone($City);
  $timeCity = new DateTime('now', $dtz);
  return ($dtz->getOffset($timeCity));
}

function checkExist($_array, $_new_record) {
  $c = count($_array);
  for ($n = $c - 1; $n > $c - 10; $n--) {
    if ($_array[$n]['ts'] == $_new_record['ts'] && $_array[$n]['lat'] == $_new_record['lat'] && $_array[$n]['lon'] == $_new_record['lon'] && $_array[$n]['distance'] == $_new_record['distance']) {
      return 1;
    }
  }
  return 0;
}

try {
  require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

  if (!jeedom::apiAccess(init('apikey'), 'blitzortung')) { //remplacez template par l'id de votre plugin
    echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
    die();
  }
  if (init('test') != '') {
    echo 'OK';
    die();
  }

  $result = file_get_contents("php://input");
  if ($result == '"error serveur"') {
    //log::add('blitzortung', 'error', 'Erreur de connexion, vérifier les log du daemon et vos identifiants');
    die();
  }

  log::add('blitzortung', 'debug', 'json : ' . $result);

  $result_array = json_decode($result, true);
  if (!is_array($result_array)) {
    die();
  }

  //$UTC_offset = getUTCoffset('Europe/Paris'); // Calcul de la timezone par rapport à UTC pour décalage des données
  //log::add('blitzortung', 'info', 'UTC offset : ' . $UTC_offset);

  if (isset($result_array['time'])) {
    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      $latitude = blitzortung::getLatitude($eqLogic);
      $longitude = blitzortung::getLongitude($eqLogic);
      $rayon = $eqLogic->getConfiguration('cfg_rayon', 50);
      //log::add('blitzortung', 'info', 'latitude configurée : '.$latitude);
      //log::add('blitzortung', 'info', 'longitude configurée : '.$longitude);          
      if ($latitude != '' && $longitude != '') {
        $distance = getDistanceBetweenPoints($latitude, $longitude, $result_array['lat'], $result_array['lon'], 'kilometres');
        if ($distance <= $rayon) {
          $ts_local = round($result_array['time'] / 1000000000) + getUTCoffset('Europe/Paris'); // Convert nano to secondes with UTC offset

          log::add('blitzortung', 'debug', ' > json : ' . $result);

          $json = $eqLogic->getConfiguration("json_impacts");
          $arr = json_decode($json, true);
          $new_record = ['ts' => $ts_local, 'lat' => $result_array['lat'], 'lon' => $result_array['lon'], 'distance' => $distance];

          if (checkExist($arr, $new_record) == 0) {
            $arr[] = $new_record;
            $counter = count($arr);
            $Azimuth = getAzimuth($latitude, $longitude, $result_array['lat'], $result_array['lon']);
            log::add('blitzortung', 'info', '[' . $eqLogic->getName() . ']' . ' ' . '[' . $ts_local . ']' . ' ' . '[' . $counter . ']' . ' distance impact : ' . $distance . ' km' . ' | ' . 'lat: ' . $result_array['lat'] . ' lon: ' . $result_array['lon'] . ' (' . $Azimuth . '°)');
            $json = json_encode($arr);
            log::add('blitzortung', 'debug', ' > json_impacts : ' . $json);
            $eqLogic->setConfiguration("json_impacts", $json);
            $eqLogic->save();

            $eqLogic->checkAndUpdateCmd('lastlat', $result_array['lat']);
            $eqLogic->checkAndUpdateCmd('lastlon', $result_array['lon']);
            $eqLogic->checkAndUpdateCmd('lastdistance', $distance);
            $eqLogic->checkAndUpdateCmd('lastorientation', $Azimuth);
            $eqLogic->checkAndUpdateCmd('counter', $counter);

            //$eqLogic->refreshWidget();
          } else {
            log::add('blitzortung', 'debug', 'L\'impact ' . json_encode($new_record) . 'a déjà été capté par un autre detecteur -> non enregistré');
          }
        }
      }
    }
  } else {
    log::add('blitzortung', 'error', 'unknown message received from daemon'); //remplacez template par l'id de votre plugin
  }
} catch (Exception $e) {
  log::add('blitzortung', 'error', displayException($e)); //remplacez template par l'id de votre plugin
}
