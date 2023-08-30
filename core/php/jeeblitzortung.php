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

function distance($lat1, $lng1, $lat2, $lng2, $unit = 'k') {
  // https://numa-bord.com/miniblog/php-calcul-de-distance-entre-2-coordonnees-gps-latitude-longitude/
  $earth_radius = 6378137;   // Terre = sphère de 6378km de rayon
  $rlo1 = deg2rad($lng1);
  $rla1 = deg2rad($lat1);
  $rlo2 = deg2rad($lng2);
  $rla2 = deg2rad($lat2);
  $dlo = ($rlo2 - $rlo1) / 2;
  $dla = ($rla2 - $rla1) / 2;
  $a = (sin($dla) * sin($dla)) + cos($rla1) * cos($rla2) * (sin($dlo) * sin($dlo));
  $d = 2 * atan2(sqrt($a), sqrt(1 - $a));
  $meter = ($earth_radius * $d);
  if ($unit == 'k') {
      return round($meter / 1000,2);
  }
  return round($meter,2);
}

function getAzimuth($_latitude1, $_longitude1, $_latitude2, $_longitude2) {
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

  if (!jeedom::apiAccess(init('apikey'), 'blitzortung')) {
    echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
    die();
  }
  if (init('test') != '') {
    echo 'OK';
    die();
  }

  $result = file_get_contents("php://input");
  if ($result == '"error serveur"' || $result == '') {
    die();
  }

  log::add('blitzortung', 'debug', ' > json : ' . $result);
  if ($result[0] != '{') {
    $result = '[' . substr($result, 1, -2) . ']';
    $result = str_replace('\'', '"', $result);
  }
  log::add('blitzortung', 'debug', ' > json after cleaning: ' . $result);

  $result_array = json_decode($result, true);
  if (!is_array($result_array)) {
    die();
  }

  //$result_array = $result_array['blitzortung']['impacts'];

  //$UTC_offset = getUTCoffset('Europe/Paris'); // Calcul de la timezone par rapport à UTC pour décalage des données
  //log::add('blitzortung', 'info', 'UTC offset : ' . $UTC_offset);

  if (isset($result_array['time'])) { // Vérification du bon format de la chaine en regardant si time existe
    $result_array[0] = $result_array; // Transformation en un tableau multidim si c'est une chaine avec une seule entrée (fonctionnement en temps réel)
    $count_impacts = 1;
  } else {
    $count_impacts = count($result_array);
  }

  if (isset($result_array[0]['time'])) { // Vérification du bon format de la chaine en regardant si time existe    
    $time_start = microtime(true);
    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      $latitude = blitzortung::getLatitude($eqLogic);
      $longitude = blitzortung::getLongitude($eqLogic);
      $rayon = $eqLogic->getConfiguration('cfg_rayon', 50);

      if ($latitude != '' && $longitude != '') {
        foreach ($result_array as $a) { // Parcours des enregistrements
          #$distance = getDistanceBetweenPoints($latitude, $longitude, $a['lat'], $a['lon'], 'kilometres'); // Analyse de la distance de l'impact
          $distance = distance($latitude, $longitude, $a['lat'], $a['lon'], 'k'); // Analyse de la distance de l'impact (autre calcul)
          if ($distance <= $rayon) {
            $ts_local = round($a['time'] / 1000000000) + getUTCoffset('Europe/Paris'); // Convert nano to secondes with UTC offset            

            //$json = $eqLogic->getConfiguration("json_impacts");
            $keyName = 'json_impacts';
            $json = cache::byKey('blitzortung::' . $eqLogic->getId() . '::' . $keyName)->getValue('');
            $arr = json_decode($json, true);
            $Azimuth = getAzimuth($latitude, $longitude, $a['lat'], $a['lon']); // Récupération de l'azimuth pour indiquer sur la boussole
            $new_record = ['ts' => $ts_local, 'lat' => $a['lat'], 'lon' => $a['lon'], 'distance' => $distance, 'azimuth' => $Azimuth];

            if (checkExist($arr, $new_record) == 0) { // Vérification pour savoir si l'impact a déjà été transmis parmis les 10 derniers pour écarter des enregistrements identiques
              $arr[] = $new_record;
              $counter = count($arr);
              
              log::add('blitzortung', 'info', '[' . $eqLogic->getName() . ']' . ' ' . '[' . $ts_local . ']' . ' ' . '[' . $counter . ']' . ' distance impact : ' . $distance . ' km' . ' | ' . 'lat: ' . $a['lat'] . ' lon: ' . $a['lon'] . ' (' . $Azimuth . '°)');    
              $json = json_encode($arr);
              log::add('blitzortung', 'debug', ' > json_impacts : ' . $json);
              //$eqLogic->setConfiguration("json_impacts", $json);
              cache::set('blitzortung::' . $eqLogic->getId() . '::' . $keyName, $json);
              $eqLogic->save();

              $eqLogic->checkAndUpdateCmd('lastlat', $a['lat']);
              $eqLogic->checkAndUpdateCmd('lastlon', $a['lon']);
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
    }
    $time_end = microtime(true);
    $time = round($time_end - $time_start, 4);
    $cycle = config::byKey('cycle', 'blitzortung', '5');
    log::add('blitzortung', 'debug', 'Temps de traitement pour ' . $count_impacts . ' impacts : ' . $time . ' seconde(s)');
    if ($cycle > 0 && $time > $cycle) {
      if (is_object($eqLogic->getCmd('info', 'timetoprocessexceeded'))) {
        $newtimetoprocessexceeded =  $eqLogic->getCmd('info', 'counter') + 1;
        $eqLogic->checkAndUpdateCmd('timetoprocessexceeded', $newtimetoprocessexceeded);
      }
      log::add('blitzortung', 'info', 'Attention, le délais de traitement pour ' . $count_impacts . ' impacts est de ' . $time . ' seconde(s) et dépasse la durée du cycle de ' . $cycle . ' seconde(s) -> Augmentez la durée et redémarrez le démon');
    }
  } else {
    log::add('blitzortung', 'error', 'unknown message received from daemon');
  }
} catch (Exception $e) {
  log::add('blitzortung', 'error', displayException($e));
}
