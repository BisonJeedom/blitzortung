<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class blitzortung extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  public static function setupCron($creation) {
    if ($creation == 1) {
      $oCron = cron::byClassAndFunction(__CLASS__, 'blitzortungCron');
      if (!is_object($oCron)) {
        $oCron = new cron();
        $oCron->setClass('blitzortung');
        $oCron->setFunction('blitzortungCron');
        $oCron->setEnable(1);
        $oCron->setSchedule('*/5 * * * *');
        $oCron->setTimeout('2');
        $oCron->save();
      }
    } else {
      $oCron = cron::byClassAndFunction(__CLASS__, 'blitzortungCron');
      if (is_object($oCron)) {
        $oCron->remove();
      }
    }
  }

  public static function getUTCoffset($_city) {
    $dtz = new DateTimeZone($_city);
    $timeCity = new DateTime('now', $dtz);
    return ($dtz->getOffset($timeCity));
  }

  public static function getLatitude($_eqLogic) {
    $latitude = $_eqLogic->getConfiguration('cfg_latitude', '');
    $latitude = ($latitude == '') ? config::bykey('info::latitude') : $latitude;
    return $latitude;
  }

  public static function getLongitude($_eqLogic) {
    $longitude = $_eqLogic->getConfiguration('cfg_longitude', '');
    $longitude = ($longitude == '') ? config::bykey('info::longitude') : $longitude;
    return $longitude;
  }

  public static function isValidLatitude($latitude) {
    if (preg_match("/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/", $latitude)) {
      return true;
    } else {
      return false;
    }
  }

  public static function isValidLongitude($longitude) {
    if (preg_match("/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/", $longitude)) {
      return true;
    } else {
      return false;
    }
  }

  public static function isBeta($text = false) {
    $plugin = plugin::byId('blitzortung');
    $update = $plugin->getUpdate();
    $isBeta = false;
    if (is_object($update)) {
      $version = $update->getConfiguration('version');
      $isBeta = ($version && $version != 'stable');
    }

    if ($text) {
      return $isBeta ? 'beta' : 'stable';
    }
    return $isBeta;
  }

  public static function getDocumentation() {
    $plugin = plugin::byId('blitzortung');
    if (self::isBeta()) {
      return $plugin->getDocumentation_beta();
    } else {
      return $plugin->getDocumentation();
    }
  }

  public static function getFurthestPointsWithPointsAndDistance() {
    $R = 6371;

    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      $latitude1 = blitzortung::getLatitude($eqLogic);
      $longitude1 = blitzortung::getLongitude($eqLogic);
      $rayon = $eqLogic->getConfiguration('cfg_rayon', 50);
      $rayon = $rayon + 10; // marge de 10km supplémentaire à transmettre au daemon python

      $lat1 = deg2rad($latitude1);
      $lon1 = deg2rad($longitude1);

      $a = deg2rad(0);
      $lat2 = asin(sin($lat1) * cos($rayon / $R) + cos($lat1) * sin($rayon / $R) * cos($a));
      $lon2 = $lon1 + atan2(sin($a) * sin($rayon / $R) * cos($lat1), cos($rayon / $R) - sin($lat1) * sin($lat2));
      $arr['"' . $eqLogic->getId() . '"']['"lat_max"'] = rad2deg($lat2);

      $a = deg2rad(90);
      $lat2 = asin(sin($lat1) * cos($rayon / $R) + cos($lat1) * sin($rayon / $R) * cos($a));
      $lon2 = $lon1 + atan2(sin($a) * sin($rayon / $R) * cos($lat1), cos($rayon / $R) - sin($lat1) * sin($lat2));
      $arr['"' . $eqLogic->getId() . '"']['"lon_max"'] = rad2deg($lon2);

      $a = deg2rad(180);
      $lat2 = asin(sin($lat1) * cos($rayon / $R) + cos($lat1) * sin($rayon / $R) * cos($a));
      $lon2 = $lon1 + atan2(sin($a) * sin($rayon / $R) * cos($lat1), cos($rayon / $R) - sin($lat1) * sin($lat2));
      $arr['"' . $eqLogic->getId() . '"']['"lat_min"'] = rad2deg($lat2);

      $a = deg2rad(270);
      $lat2 = asin(sin($lat1) * cos($rayon / $R) + cos($lat1) * sin($rayon / $R) * cos($a));
      $lon2 = $lon1 + atan2(sin($a) * sin($rayon / $R) * cos($lat1), cos($rayon / $R) - sin($lat1) * sin($lat2));
      $arr['"' . $eqLogic->getId() . '"']['"lon_min"'] = rad2deg($lon2);
    }

    return json_encode($arr);
  }

  public static function evalExpr($_expr) {
    try {
      return jeedom::evaluateExpression($_expr) == 1 ? 1 : 0;
    } catch (Exception $e) {
      log::add('blitzortung', 'error', 'Impossible d\'évaluer le paramètre Expression "déclenchant l\'écoute des évènements"');
      return 0;
    }
  }

  public static function blitzortungCron() {
    $event_to_send = 'stop';
    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      if ($eqLogic->getIsEnable()) {
        //$json = $eqLogic->getConfiguration("json_impacts");
        $keyName = 'json_impacts';
        $json = cache::byKey('blitzortung::' . $eqLogic->getId() . '::' . $keyName)->getValue('');
        $LastImpactRetention = $eqLogic->getConfiguration("cfg_LastImpactRetention", 1);

        log::add('blitzortung', 'info', '[Start] Nettoyage des enregistrements de ' . $eqLogic->getName());
        log::add('blitzortung', 'info', '| Durée de conservation : ' . $LastImpactRetention . ' h');

        $arr = json_decode($json, true);
        $count_start = count($arr);
        $ts_limit = time() + self::getUTCoffset('Europe/Paris') - 3600 * $LastImpactRetention; // Heure actuelle moins le délais de rétention
        $ts_limit_5mn = time() + self::getUTCoffset('Europe/Paris') - 300;
        $ts_limit_10mn = time() + self::getUTCoffset('Europe/Paris') - 600;
        $ts_limit_15mn = time() + self::getUTCoffset('Europe/Paris') - 900;

        log::add('blitzortung', 'debug', '| TS LIMIT : ' . $ts_limit);
        log::add('blitzortung', 'debug', '| TS LIMIT 15mn : ' . $ts_limit_15mn);
        log::add('blitzortung', 'debug', '| TS LIMIT 10mn : ' . $ts_limit_10mn);
        log::add('blitzortung', 'debug', '| TS LIMIT 5mn : ' . $ts_limit_5mn);

        log::add('blitzortung', 'debug', '| Impacts enregistrés : ' . $json);
        log::add('blitzortung', 'debug', '| Nombre d\'enregistrement  : ' . $count_start);

        $new_arr = array();
        $average_arr = array();
        $i = 0;

        foreach ($arr as $key => $value) {
          if ($value["ts"] < $ts_limit) {
            log::add('blitzortung', 'debug', '| ' . $value["ts"] . ' < ' . $ts_limit . ' removing entry ' . $key);
          } else {
            $new_arr[] = $value;
          }
          // $average_arr[0] : -15mn -> -10mn ;  $average_arr[1] : -10mn -> -5mn ; $average_arr[2] : -5mn -> 0mn
          if ($value["ts"] > $ts_limit_15mn) { // Si le TS est dans les 5 dernières minutes
            if ($value["ts"] < $ts_limit_10mn) {
              $average_arr[0][0]++;
              $average_arr[0][1] = $average_arr[0][1] + $value["distance"];
              log::add('blitzortung', 'debug', '| ' .  '[-15mn -> -10mn] ' . $value["ts"] . ' ' . $value["distance"] . ' km');
            } elseif ($value["ts"] < $ts_limit_5mn) {
              $average_arr[1][0]++;
              $average_arr[1][1] = $average_arr[1][1] + $value["distance"];
              log::add('blitzortung', 'debug', '| ' .  '[-10mn -> -5mn] ' . $value["ts"] . ' ' . $value["distance"] . ' km');
            } else {
              $average_arr[2][0]++;
              $average_arr[2][1] = $average_arr[2][1] + $value["distance"];
              log::add('blitzortung', 'debug', '| ' .  '[-5mn -> -0mn] ' . $value["ts"] . ' ' . $value["distance"] . ' km');
            }
          }
        }

        // Analyse de l'évolution de l'orage //
        for ($i = 0; $i < 3; $i++) {
          $average_arr[$i][0] = (!isset($average_arr[$i][0]) || $average_arr[$i][0] == '') ? 0 : $average_arr[$i][0];
          $average_arr[$i][1] = ($average_arr[$i][0] == 0) ? 0 : round($average_arr[$i][1] / $average_arr[$i][0], 2);
        }
        log::add('blitzortung', 'info', '| [-15mn -> -10mn] : Moyenne de ' .  $average_arr[0][1] . ' km ' . '(' . $average_arr[0][0] . ' impacts)');
        log::add('blitzortung', 'info', '| [-10mn -> -5mn] : Moyenne de ' .  $average_arr[1][1] . ' km ' . '(' . $average_arr[1][0] . ' impacts)');
        log::add('blitzortung', 'info', '| [-5mn -> -0mn] : Moyenne de ' .  $average_arr[2][1] . ' km ' . '(' . $average_arr[2][0] . ' impacts)');

        $evolution_impacts = false;
        $evolution_distance = false;
        if ($average_arr[0][0] > $average_arr[1][0] && $average_arr[1][0] >= $average_arr[2][0]) {
          log::add('blitzortung', 'info', '| Nombre d\'impacts en diminution');
          $evolution_impacts = true;
          $eqLogic->checkAndUpdateCmd('counterevolution', -1);
        }
        if ($average_arr[0][0] < $average_arr[1][0] && $average_arr[1][0] <= $average_arr[2][0]) {
          log::add('blitzortung', 'info', '| Nombre d\'impacts en augmentation');
          $evolution_impacts = true;
          $eqLogic->checkAndUpdateCmd('counterevolution', 1);
        }
        if ($average_arr[0][1] > $average_arr[1][1] && $average_arr[1][1] >= $average_arr[2][1]) {
          log::add('blitzortung', 'info', '| L\'orage se rapproche');
          $evolution_distance = true;
          $eqLogic->checkAndUpdateCmd('distanceevolution', 1);
        }
        if ($average_arr[0][0] < $average_arr[1][0] && $average_arr[1][0] < $average_arr[2][0]) {
          log::add('blitzortung', 'info', '| L\'orage s\'éloigne');
          $evolution_distance = true;
          $eqLogic->checkAndUpdateCmd('distanceevolution', -1);
        }
        if ($evolution_distance === false) {
          $eqLogic->checkAndUpdateCmd('distanceevolution', 0);
        }
        if ($evolution_impacts === false) {
          $eqLogic->checkAndUpdateCmd('counterevolution', 0);
        }

        $count_end = count($new_arr);
        if ($count_end == 0) {
          $eqLogic->checkAndUpdateCmd('lastdistance', '');
        }
        log::add('blitzortung', 'debug', '| Impacts enregistrés : ' . $json);
        log::add('blitzortung', 'debug', '| Nombre d\'enregistrement  : ' . $count_end);

        $delete_record = $count_start - $count_end;
        log::add('blitzortung', 'info', '| Suppression de ' . $delete_record . ' enregistrements');
        log::add('blitzortung', 'info', '| [End] Nettoyage des enregistrements de ' . $eqLogic->getName());

        $json = json_encode($new_arr);
        //$eqLogic->setConfiguration("json_impacts", $json);
        cache::set('blitzortung::' . $eqLogic->getId() . '::' . $keyName, $json);
        $eqLogic->checkAndUpdateCmd('counter', $count_end);
        $eqLogic->setConfiguration("evolution", 'Evolution sur 15 minutes : ' . $evolution_impacts . ' --- ' . $evolution_distance);
        $eqLogic->save();

        // Vérification de la probabilité d'un orage pour activer l'écoute coté démon
        log::add('blitzortung', 'info', '[Start] Récupération de la probabilité d\'un orage pour ' . $eqLogic->getName());
        $cfg_CmdtoListen = $eqLogic->getConfiguration("cfg_CmdtoListen");
        $proba = 0;
        if ($cfg_CmdtoListen != '') {
          $expr = $eqLogic->evalExpr($cfg_CmdtoListen); // Evaluation de la condition sur la probabilité d'un orage
          log::add('blitzortung', 'info', '| Probabilité d\'un orage : ' . $expr);
          if ($expr == 1) {
            $event_to_send = 'start';
            $proba = 1;
          }
        } else {
          log::add('blitzortung', 'info', '| Aucune commande liée à la probabilité d\'un orage');
          $event_to_send = 'start';
        }
        log::add('blitzortung', 'info', '[End] Récupération de la probabilité d\'un orage pour ' . $eqLogic->getName());
        cache::set('blitzortung::' . $eqLogic->getName() . '::event', $proba);
        $eqLogic->refreshWidget();
      }
    }

    $event_running = cache::byKey('blitzortung::blitzortung::event')->getValue('');
    if ($event_to_send == 'start' && ($event_running == 'stop' || $event_running == '')) {
      $params['cmd']  = 'start';
      $eqLogic->sendToDaemon($params);
      log::add('blitzortung', 'info', 'Démarrage de l\'écoute envoyée au démon');
      cache::set('blitzortung::blitzortung::event', 'start');
    }
    if ($event_to_send == 'stop' && $event_running == 'start') {
      $params['cmd']  = 'stop';
      $eqLogic->sendToDaemon($params);
      log::add('blitzortung', 'info', 'Arrêt de l\'écoute envoyée au démon');
      cache::set('blitzortung::blitzortung::event', 'stop');
    }
  }

  public static function getFreePort() {
    $freePortFound = false;
    while (!$freePortFound) {
      $port = mt_rand(50000, 65000);
      exec('sudo fuser ' . $port . '/tcp', $out, $return);
      if ($return == 1) {
        $freePortFound = true;
      }
    }
    config::save('socketport', $port, 'blitzortung');
    return $port;
  }

  public function CreateCmd($_eqlogic, $_name, $_template, $_histo, $_historound, $_histomode, $_histopurge, $_repeatEvent, $_generictype, $_type, $_subtype, $_unite, $_visible) {
    $info = $this->getCmd(null, $_eqlogic);
    if (!is_object($info)) {
      $info = new blitzortungCmd();
      $info->setName(__($_name, __FILE__));
      if (!empty($_template)) {
        $info->setTemplate('dashboard', $_template);
      }
      if (!empty($_histo)) {
        $info->setIsHistorized($_histo);
      }
      if (!empty($_historound)) {
        $info->setConfiguration('historizeRound', $_historound);
      }
      if (!empty($_histomode)) {
        $info->setConfiguration('historizeMode', $_histomode);
      }
      if (!empty($_histopurge)) {
        $info->setConfiguration('historyPurge', $_histopurge);
      }
      if (!empty($_repeatEvent)) {
        $info->setConfiguration('repeatEventManagement', $_repeatEvent);
      }
      if (!empty($_generictype)) {
        $info->setGeneric_type($_generictype);
      }
      $info->setEqLogic_id($this->getId());
      $info->setLogicalId($_eqlogic);
      if (!empty($_type)) {
        $info->setType($_type);
      }
      if (!empty($_subtype)) {
        $info->setSubType($_subtype);
      }
      if (!empty($_unite)) {
        $info->setUnite($_unite);
      }
      $info->setIsVisible($_visible);
      $info->save();
    }
  }


  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
    $this->setConfiguration('cfg_LastImpactRetention', '1');
    $this->setConfiguration('cfg_ImpactsRecents', '1');
    $this->setConfiguration('cfg_Zoom', '10');
    $this->setConfiguration('cfg_TemplateName', 'horizontal');
    $this->setConfiguration('cfg_DefaultChart', '1');
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
    //$_savedconfiguration = json_decode($this->getConfiguration('_savedconfiguration'), true);
    //log::add(__CLASS__, 'info', 'old latitude : ' . $_savedconfiguration['latitude']);
    //log::add(__CLASS__, 'info', 'old longitude : ' . $_savedconfiguration['longitude']);
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    $this->CreateCmd('refresh', 'Rafraichir', '', '0', '', '', '', '', '', 'action', 'other', '', '1');
    $this->CreateCmd('lastlat', 'Dernière latitude', '', '0', '', '', '', '', '', 'info', 'string', '', '1');
    $this->CreateCmd('lastlon', 'Dernière longitude', '', '0', '', '', '', '', '', 'info', 'string', '', '1');
    $this->CreateCmd('lastdistance', 'Dernière distance', '', '1', '2', 'none', '-1 month', 'always', '', 'info', 'numeric', 'km', '1');
    $this->CreateCmd('lastorientation', 'Dernière orientation', '', '0', '2', '', '', '', '', 'info', 'numeric', '°', '1');
    $this->CreateCmd('distanceevolution', 'Evolution de la distance sur 15mn', '', '1', '', 'none', '-1 month', '', '', 'info', 'numeric', '', '1');
    $this->CreateCmd('counter', 'Compteur des impacts', '', '0', '', '', '', '', '', 'info', 'numeric', '', '1');
    $this->CreateCmd('counterevolution', 'Evolution des impacts sur 15mn', '', '1', '', 'none', '-1 month', '', '', 'info', 'numeric', '', '1');
    $this->CreateCmd('timetoprocessexceeded', 'Délai de traitement trop important', '', '', '', '', '', '', '', 'info', 'numeric', '', '1');
    $this->CreateCmd('mapurl', 'URL de la carte', '', '0', '', '', '', '', '', 'info', 'string', '', '1');
    $this->checkAndUpdateCmd('mapurl', 'https://map.blitzortung.org/#' . $this->getConfiguration("cfg_Zoom", 10) . '/' . self::getLatitude($this) . '/' . self::getLongitude($this));

    if ($this->getConfiguration('latChanged') == 'true' || $this->getConfiguration('lonChanged') == 'true' || $this->getConfiguration('rayonChanged') == 'true') {
      log::add('blitzortung', 'debug', 'latChanged : ' . $this->getConfiguration('latChanged'));
      log::add('blitzortung', 'debug', 'lonChanged : ' . $this->getConfiguration('lonChanged'));
      log::add('blitzortung', 'debug', 'rayonChanged : ' . $this->getConfiguration('rayonChanged'));
      $this->setConfiguration('latChanged', '');
      $this->setConfiguration('lonChanged', '');
      $this->setConfiguration('rayonChanged', '');
      $this->save(true); // Save pour enregister les données brutes sans repasser par PRE & POST sinon ça boucle sur postSave()
      log::add('blitzortung', 'info', 'Changement de la configuration -> Redémarrage du démon');
      self::deamon_start();
    }
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = log::getPathToLog(__CLASS__ . '_update');
    $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
    if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
      $return['state'] = 'in_progress';
    } else {
      if (exec(system::getCmdSudo() . system::get('cmd_check') . '-Ec "python3\-pyudev') < 1) {
        $return['state'] = 'nok';
      } elseif (exec(system::getCmdSudo() . 'pip3 list | grep -Ewc "websockets"') < 1) {
        $return['state'] = 'nok';
      } else {
        $return['state'] = 'ok';
      }
    }
    return $return;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
      if (@posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    }
    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      if (!self::isValidLatitude(self::getLatitude($eqLogic))) {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Latitude de ' . $eqLogic->getName() . ' incorrecte', __FILE__);
        return $return;
      }
      if (!self::isValidLongitude(self::getLongitude($eqLogic))) {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Longitude de ' . $eqLogic->getName() . ' incorrecte', __FILE__);
        return $return;
      }
      $rayon = $eqLogic->getConfiguration('cfg_rayon', '50');
      if ($rayon < 1 || $rayon > 200) {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Rayon de ' . $eqLogic->getName() . ' non accepté', __FILE__);
        return $return;
      }
    }
    $return['launchable'] = 'ok';
    $return['last_launch'] = config::byKey('lastDeamonLaunchTime', __CLASS__, __('Inconnue', __FILE__));
    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    //self::getFreePort();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }

    $MinAndMaxGPS = self::getFurthestPointsWithPointsAndDistance();
    cache::set('blitzortung::blitzortung::event', 'stop');

    $path = realpath(dirname(__FILE__) . '/../../resources/blitzortungd'); // répertoire du démon
    $cmd = 'python3 ' . $path . '/blitzortungd.py'; // nom du démon
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
    $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, '56023'); // port par défaut
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/blitzortung/core/php/jeeblitzortung.php'; // chemin de la callback url à modifier (voir ci-dessous)
    //$cmd .= ' --latitude "' . $latitude .'"';
    //$cmd .= ' --longitude "' . $longitude .'"';
    $cmd .= ' --MinAndMaxGPS "' . $MinAndMaxGPS . '"';
    $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__); // l'apikey pour authentifier les échanges suivants
    $cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__, '5'); // cycle d'envoi des données vers Jeedom
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // chemin vers le pid file
    log::add(__CLASS__, 'info', 'Exécution du démon');
    $result = exec($cmd . ' >> ' . log::getPathToLog('blitzortungd') . ' 2>&1 &'); // nom du log pour le démon
    $i = 0;
    while ($i < 20) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 20) {
      log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
      return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');
    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('blitzortungd.py'); // nom du démon
    sleep(1);
  }

  public static function sendToDaemon($params) {
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] != 'ok') {
      throw new Exception("Le démon n'est pas démarré");
    }
    $params['apikey'] = jeedom::getApiKey(__CLASS__);
    $payLoad = json_encode($params);
    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '56023'));
    socket_write($socket, $payLoad, strlen($payLoad));
    socket_close($socket);
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  */
  public function toHtml($_version = 'dashboard') {
    $TemplateName = $this->getConfiguration("cfg_TemplateName", "horizontal"); // Récupération du template choisi (par défaut : horizontal)
    if ($TemplateName == 'aucun') {
      return parent::toHtml($_version);
    }

    $replace = $this->preToHtml($_version); // initialise les tag standards : #id#, #name# ...

    if (!is_array($replace)) {
      return $replace;
    }

    $version = jeedom::versionAlias($_version);

    $eqLogicName = $this->getName();
    log::add('blitzortung', 'debug', '[template] Affichage du template pour ' . $eqLogicName . ' [START]');

    //$json = $this->getConfiguration("json_impacts");
    $keyName = 'json_impacts';
    $json = cache::byKey('blitzortung::' . $this->getId() . '::' . $keyName)->getValue('');
    $rayon = $this->getConfiguration('cfg_rayon', 50);
    $LastImpactRetention = $this->getConfiguration("cfg_LastImpactRetention", 1);
    $tsmax = $LastImpactRetention * 3600; // Valeur maximum sur le graphique (en secondes)

    $arr = json_decode($json, true);

    $replace['#data#'] = '';
    $replace['#datapolar_recent#'] = '';
    $replace['#datapolar_lessrecent#'] = '';
    $cfg_ImpactsRecents = $this->getConfiguration("cfg_ImpactsRecents", 1);
    $ts_limit = time() + self::getUTCoffset('Europe/Paris') - $cfg_ImpactsRecents * 300; // pour avoir les impacts des 5, 10 ou 15 dernieres minutes suivant la configuration
    //log::add('blitzortung', 'info', 'ts_limit : ' . $ts_limit);
    foreach ($arr as $key => $value) {
      $ts = time() + self::getUTCoffset('Europe/Paris') - $value["ts"]; // Délais depuis l'enregistrement en secondes      
      $replace['#data#'] .= '[' . $ts . ',' . $value["distance"] . ']' . ',';
      $azimuth = $value["azimuth"] < 0 ? 360 + $value["azimuth"] : $value["azimuth"]; // Transforme un azimuth négatif en valeur comprise entre 0 et 360° pour l'affichage
      if ($value["ts"] > $ts_limit) {
        $replace['#datapolar_recent#'] .= '[' . $azimuth . ',' . $value["distance"] . ']' . ','; // dans les 5 dernières minutes
      } else {
        $replace['#datapolar_lessrecent#'] .= '[' . $azimuth . ',' . $value["distance"] . ']' . ',';
      }
    }
    //log::add('blitzortung', 'info', 'data : ' . $replace['#data#']);    
    $replace['#data#'] = substr($replace['#data#'], 0, -1);
    $replace['#datapolar_recent#'] = substr($replace['#datapolar_recent#'], 0, -1);
    $replace['#datapolar_lessrecent#'] = substr($replace['#datapolar_lessrecent#'], 0, -1);

    //log::add('blitzortung', 'info', 'recent : ' . $replace['#datapolar_recent#']);
    //log::add('blitzortung', 'info', 'lessrecent : ' . $replace['#datapolar_lessrecent#']);

    $replace['#rayon#'] = $rayon;
    $replace['#retention#'] = $LastImpactRetention;
    $replace['#tsmax#'] = $tsmax;

    // Passage d'un tableau pour définir les positions des ticks sur les abscisses
    $i = $LastImpactRetention * 6;
    $m = ($i == 18) ? 2 : (($i == 24) ? 3 : 1); // Tick toutes les 10mn si 1h ou 2h, 20mn si 3h et 30mn si 4h pour continuer de voir les ticks si fenêtre réduite au maximum

    $replace['#tickPositions#'] = '';
    for ($j = 0; $j <= $i; $j++) {
      $k = ($j * 600) * $m; // l'affichage est divisé par 60 pour afficher en minutes
      $replace['#tickPositions#'] .= $k . ',';
    }
    $replace['#tickPositions#'] = substr($replace['#tickPositions#'], 0, -1);


    // Gestion de l'URL de la carte à ouvrir
    if (is_object($this->getCmd('info', 'mapurl'))) {
      $replace['#mapurl#'] = $this->getCmd('info', 'mapurl')->execCmd();
    } else {
      $replace['#mapurl#'] = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : mapurl -> Merci de vérifier puis sauvegarder pour générer la commande');
    }


    // Gestion du nombre d'impact pour mise à jour du widget
    if (is_object($this->getCmd('info', 'counter'))) {
      $cmd = $this->getCmd('info', 'counter');
      $replace['#counter_id#'] = $cmd->getId();
      $replace['#counter_value#'] = $cmd->execCmd();
      $replace['#counter_valueDate#'] = $cmd->getValueDate();
      $replace['#counter_collectDate#'] = $cmd->getCollectDate();
    } else {
      $replace['#counter_id#'] = '';
      $replace['#counter_value#'] = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : counter -> Merci de vérifier puis sauvegarder pour générer la commande');
    }

    // Gestion de la distance pour mise à jour du widget
    if (is_object($this->getCmd('info', 'lastdistance'))) {
      $cmd = $this->getCmd('info', 'lastdistance');
      $distance = $cmd->execCmd();
      $replace['#distance_id#'] = $cmd->getId();
    } else {
      $distance = '';
      $replace['#distance_id#'] = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : lastdistance -> Merci de vérifier puis sauvegarder pour générer la commande');
    }
    $replace['#distance_value#'] = $distance;
    $replace['#distance_unit#'] = 'km';
    $replace['#distance_valueDate#'] = $cmd->getValueDate();
    $replace['#distance_collectDate#'] = $cmd->getCollectDate();
    if ($distance != '') {
      if ($distance <= 10) {
        $replace['#circlecolorValue#'] = '#EA251F'; // Cercle en rouge
      } elseif ($distance <= 30) {
        $replace['#CirclecolorValue#'] = '#EA6E1E'; // Cercle en orange
      } else {
        $replace['#CirclecolorValue#'] = '#DFE150'; // Cercle en jaune
      }
    } else {
      $replace['#CirclecolorValue#'] = '#3A5B8F'; // Cercle en bleu
    }

    // Gestion de l'orientation du dernier impact (en degrés)
    if (is_object($this->getCmd('info', 'lastorientation'))) {
      $cmd = $this->getCmd('info', 'lastorientation');
      $orientation = $cmd->execCmd();
      $replace['#lastorientation_id#'] = $cmd->getId();
    } else {
      $replace['#lastorientation_id#'] = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : lastorientation -> Merci de vérifier puis sauvegarder pour générer la commande');
    }
    $orientation = ($orientation == '') ? 0 : $orientation;
    $replace['#lastorientation_value#'] = $orientation;

    // Gestion du compteur d'impacts pour evolution sur 15mn
    if (is_object($this->getCmd('info', 'counterevolution'))) {
      $cmd = $this->getCmd('info', 'counterevolution');
      $counterevolution = $cmd->execCmd();
    } else {
      $counterevolution = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : counterevolution -> Merci de vérifier puis sauvegarder pour générer la commande');
    }
    if ($counterevolution == -1) {
      $replace['#counterevolution_value#'] = 'Diminution';
    } elseif ($counterevolution == 1) {
      $replace['#counterevolution_value#'] = 'Augmentation';
    } else {
      $replace['#counterevolution_value#'] = '---';
    }
    $replace['#counterevolution_id#'] = $cmd->getId();

    // Gestion de la distance pour evolution sur 15mn
    if (is_object($this->getCmd('info', 'distanceevolution'))) {
      $cmd = $this->getCmd('info', 'distanceevolution');
      $distanceevolution = $cmd->execCmd();
      $replace['#distanceevolution_id#'] = $cmd->getId();
    } else {
      $distanceevolution = '';
      $replace['#distanceevolution_id#'] = '';
      log::add(__CLASS__, 'error', 'Commande manquante sur l\'équipement ' . $eqLogicName . ' : distanceevolution -> Merci de vérifier puis sauvegarder pour générer la commande');
    }
    if ($distanceevolution == -1) {
      $replace['#distanceevolution_value#'] = 'Eloignement';
    } elseif ($distanceevolution == 1) {
      $replace['#distanceevolution_value#'] = 'Rapprochement';
    } else {
      $replace['#distanceevolution_value#'] = '---';
    }

    // Graphique actif
    $cfg_DefaultChart = $this->getConfiguration("cfg_DefaultChart", 1);
    if ($cfg_DefaultChart == 1) {
      $replace['#item1active#'] = 'active';
      $replace['#item2active#'] = '';
    } else {
      $replace['#item1active#'] = '';
      $replace['#item2active#'] = 'active';
    }


    //$b = cache::byKey('blitzortung::' . $this->getName() . '::event')->getValue('');
    //log::add(__CLASS__, 'info', $this->getName() . ' : ' . $b);
    $replace['#proba-blitz_id#'] = cache::byKey('blitzortung::' . $this->getName() . '::event')->getValue('');

    $getTemplate = getTemplate('core', $version, 'blitzortung_' . $TemplateName . '.template', __CLASS__); // on récupère le template du plugin.
    $template_replace = template_replace($replace, $getTemplate); // on remplace les tags
    $postToHtml = $this->postToHtml($_version, $template_replace); // on met en cache le widget, si la config de l'user le permet.  
    log::add('blitzortung', 'debug', '[template] Affichage du template pour ' . $eqLogicName . ' [END]');
    return $postToHtml; // renvoie le code du template.

  }


  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*     * **********************Getteur Setteur*************************** */
}

class blitzortungCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic(); // récupère l'éqlogic de la commande $this
    switch ($this->getLogicalId()) { // vérifie le logicalid de la commande      
      case 'refresh': // LogicalId de la commande rafraîchir
        $eqLogic->blitzortungCron();
        break;
      default:
        log::add('blitzortung', 'debug', 'Erreur durant le raffraichissement');
        break;
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}
