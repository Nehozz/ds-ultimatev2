<?php

namespace App\Http\Controllers;

use App\Ally;
use App\Player;
use App\Server;
use App\Util\BasicFunctions;
use App\Village;
use App\World;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DBController extends Controller
{

    public function serverTable(){
        DB::statement('CREATE DATABASE '.env('DB_DATABASE_MAIN'));
        Schema::create(env('DB_DATABASE_MAIN').'.server', function (Blueprint $table){
            $table->integer('id')->autoIncrement();
            $table->char('code');
            $table->text('url');
        });
    }

    public function logTable(){
        Schema::create(env('DB_DATABASE_MAIN').'.log', function (Blueprint $table){
            $table->bigIncrements('id')->autoIncrement();
            $table->text('type');
            $table->text('msg');
            $table->timestamps();
        });
    }

    public function worldTable(){
        Schema::create(env('DB_DATABASE_MAIN').'.world', function (Blueprint $table){
            $table->integer('id')->autoIncrement();
            $table->text('name');
            $table->integer('ally_count')->nullable();
            $table->integer('player_count')->nullable();
            $table->integer('village_count')->nullable();
            $table->text('url');
            $table->timestamps();
        });
    }

    public function playerTable($dbName, $tableName){
        Schema::create($dbName.'.player_'.$tableName, function (Blueprint $table) {
            $table->integer('playerID');
            $table->string('name');
            $table->integer('ally_id');
            $table->integer('village_count');
            $table->integer('points');
            $table->integer('rank');
            $table->bigInteger('offBash')->nullable();
            $table->integer('offBashRank')->nullable();
            $table->bigInteger('defBash')->nullable();
            $table->integer('defBashRank')->nullable();
            $table->bigInteger('gesBash')->nullable();
            $table->integer('gesBashRank')->nullable();
            $table->timestamps();
        });
    }

    public function allyTable($dbName, $tableName){
        Schema::create($dbName.'.ally_'.$tableName, function (Blueprint $table) {
            $table->integer('allyID');
            $table->string('name');
            $table->string('tag');
            $table->integer('member_count');
            $table->integer('points');
            $table->integer('village_count');
            $table->integer('rank');
            $table->bigInteger('offBash')->nullable();
            $table->integer('offBashRank')->nullable();
            $table->bigInteger('defBash')->nullable();
            $table->integer('defBashRank')->nullable();
            $table->bigInteger('gesBash')->nullable();
            $table->integer('gesBashRank')->nullable();
            $table->timestamps();
        });
    }

    public function villageTable($dbName, $tableName){
        Schema::create($dbName.'.village_'.$tableName, function (Blueprint $table) {
            $table->integer('villageID');
            $table->string('name');
            $table->integer('x');
            $table->integer('y');
            $table->integer('points');
            $table->integer('owner');
            $table->integer('bonus_id');
            $table->timestamps();
        });
    }

    public function getWorld(){
        $serverArray = Server::getServer();

        foreach ($serverArray as $serverUrl){
            $worldFile = file_get_contents($serverUrl->url.'/backend/get_servers.php');
            $worldTable = new World();
            $worldTable->setTable(env('DB_DATABASE_MAIN').'.world');
            $worldArray = unserialize($worldFile);
            foreach ($worldArray as $world => $link){
                if ($worldTable->where('name', $world)->count() < 1){
                    $worldNew = new World();
                    $worldNew->setTable(env('DB_DATABASE_MAIN').'.world');
                    $worldNew->name = $world;
                    $worldNew->url = $link;
                    if($worldNew->save() === true){
                        BasicFunctions::createLog('insert[World]', "Welt $world wurde erfolgreich der Tabelle '$world' hinzugefügt.");
                        $name = str_replace('{server}{world}', '',env('DB_DATABASE_WORLD')).$world;
                        if (DB::statement('CREATE DATABASE '.$name) === true){
                            BasicFunctions::createLog("createBD[$world]", "DB '$name' wurde erfolgreich erstellt.");
                        }else{
                            BasicFunctions::createLog("ERROR_createBD[$world]", "DB '$name' konnte nicht erstellt werden.");
                        }
                    }else{
                        BasicFunctions::createLog('ERROR_insert[World]', "Welt $world konnte nicht der Tabelle 'world' hinzugefügt werden.");
                    }
                }
            }
        }
    }

    public function latestPlayer($worldName){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 1800);
        ini_set('memory_limit', '400M');
        date_default_timezone_set("Europe/Berlin");
        $dbName = str_replace('{server}{world}', '',env('DB_DATABASE_WORLD')).$worldName;

        if (BasicFunctions::existTable($dbName, 'player_latest') === false){
            $this->playerTable($dbName, 'latest');
        }

        $time = time();
        $lines = gzfile("https://$worldName.die-staemme.de/map/player.txt.gz");
        if(!is_array($lines)) die("Datei player konnte nicht ge&ouml;ffnet werden");

        $players = collect();
        $playerOffs = collect();
        $playerDefs = collect();
        $playerTots = collect();

        foreach ($lines as $line){
            list($id, $name, $ally, $villages, $points, $rank) = explode(',', $line);
            $player = collect();
            $player->put('id', (int)$id);
            $player->put('name', $name);
            $player->put('ally', (int)$ally);
            $player->put('villages', (int)$villages);
            $player->put('points', (int)$points);
            $player->put('rank', (int)$rank);
            $players->put($player->get('id'),$player);
        }

        $offs = gzfile("https://$worldName.die-staemme.de/map/kill_att.txt.gz");
        if(!is_array($offs)) die("Datei kill_off konnte nicht ge&ouml;ffnet werden");
        foreach ($offs as $off){
            list($rank, $id, $kills) = explode(',', $off);
            $playerOff = collect();
            $playerOff->put('offRank', (int)$rank);
            $playerOff->put('off', (int)$kills);
            $playerOffs->put($id, $playerOff);
        }

        $defs = gzfile("https://$worldName.die-staemme.de/map/kill_def.txt.gz");
        if(!is_array($defs)) die("Datei kill_def konnte nicht ge&ouml;ffnet werden");
        foreach ($defs as $def){
            list($rank, $id, $kills) = explode(',', $def);
            $playerDef = collect();
            $playerDef->put('defRank', (int)$rank);
            $playerDef->put('def', (int)$kills);
            $playerDefs->put($id, $playerDef);
        }

        $tots = gzfile("https://$worldName.die-staemme.de/map/kill_all.txt.gz");
        if(!is_array($tots)) die("Datei kill_all konnte nicht ge&ouml;ffnet werden");
        foreach ($tots as $tot){
            list($rank, $id, $kills) = explode(',', $tot);
            $playerTot = collect();
            $playerTot->put('totRank', (int)$rank);
            $playerTot->put('tot', (int)$kills);
            $playerTots->put($id, $playerTot);
        }

        $insert = new Player();
        $insert->setTable($dbName.'.player_latest');
        foreach ($players as $player) {
            $id = $player->get('id');
            $dataPlayer = [
                'playerID' => $player->get('id'),
                'name' => $player->get('name'),
                'ally_id' => $player->get('ally'),
                'village_count' => $player->get('villages'),
                'points' => $player->get('points'),
                'rank' => $player->get('rank'),
                'offBash' => (is_null($playerOffs->get($id)))? null :$playerOffs->get($id)->get('off'),
                'offBashRank' => (is_null($playerOffs->get($id)))? null : $playerOffs->get($id)->get('off'),
                'defBash' => (is_null($playerDefs->get($id)))? null : $playerDefs->get($id)->get('def'),
                'defBashRank' => (is_null($playerDefs->get($id)))? null : $playerDefs->get($id)->get('defRank'),
                'gesBash' => (is_null($playerTots->get($id)))? null : $playerTots->get($id)->get('tot'),
                'gesBashRank' => (is_null($playerTots->get($id)))? null : $playerTots->get($id)->get('totRank'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
            $arrayPlayer []= $dataPlayer;
        }

        foreach (array_chunk($arrayPlayer,3000) as $t){
            $insert->insert($t);
        }

        $hashPlayer = $this->hashTablePlayer($players, $playerOffs, $playerDefs, $playerTots, 'p');

        for ($i = 0; $i < env('HASH_PLAYER'); $i++){
            if (array_key_exists($i ,$hashPlayer)) {
                if (BasicFunctions::existTable($dbName, 'player_' . $i) === false) {
                    $this->playerTable($dbName, $i);
                }
                $insert->setTable($dbName . '.player_' . $i);
                foreach (array_chunk($hashPlayer[$i], 3000) as $t) {
                    $insert->insert($t);
                }

                if (BasicFunctions::existTable($dbName, 'player_' . $i) === true) {
                    $delete = $insert->where('updated_at', '<', Carbon::createFromTimestamp(time() - (60 * 60 * 24) * env('DB_SAVE_DAY')));

                    $delete->delete();
                    echo 'test';
                }
                echo '<br>';
            }
        }

        $world = new World();
        $world->setTable(env('DB_DATABASE_MAIN').'.world');
        $worldUpdate = $world->where('name', $worldName)->first();
        $worldUpdate->player_count = count($arrayPlayer);
        $worldUpdate->save();

        echo time()-$time;
    }

    public function latestVillages($worldName){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 1800);
        ini_set('memory_limit', '500M');
        date_default_timezone_set("Europe/Berlin");
        $dbName = str_replace('{server}{world}', '', env('DB_DATABASE_WORLD')) . $worldName;
        if (BasicFunctions::existTable($dbName, 'village_latest') === false) {
            $this->villageTable($dbName, 'latest');
        }
        $time = time();
        $lines = gzfile("https://$worldName.die-staemme.de/map/village.txt.gz");
        if (!is_array($lines)) die("Datei village konnte nicht ge&ouml;ffnet werden");
        $villages = collect();
        foreach ($lines as $line) {
            list($id, $name, $x, $y, $points, $owner, $bonus_id) = explode(',', $line);
            $village = collect();
            $village->put('id', (int)$id);
            $village->put('name', $name);
            $village->put('x', (int)$x);
            $village->put('y', (int)$y);
            $village->put('points', (int)$points);
            $village->put('owner', (int)$owner);
            $village->put('bonus_id', (int)$bonus_id);
            $villages->put($village->get('id'), $village);
        }

        $insert = new Village();
        $insert->setTable($dbName . '.village_latest');
        $array = array();
        foreach ($villages as $village) {
            $data = [
                'villageID' => $village->get('id'),
                'name' => $village->get('name'),
                'x' => $village->get('x'),
                'y' => $village->get('y'),
                'points' => $village->get('points'),
                'owner' => $village->get('owner'),
                'bonus_id' => $village->get('bonus_id'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
            $array [] = $data;
        }
        foreach (array_chunk($array, 3000) as $t) {
            $insert->insert($t);
        }

        $hashVillage = $this->hashTableVillage($villages);

        for ($i = 0; $i < env('HASH_VILLAGE'); $i++) {
            if (array_key_exists($i, $hashVillage)) {
                if (BasicFunctions::existTable($dbName, 'village_' . $i) === false) {
                    $this->villageTable($dbName, $i);
                }
                $insert->setTable($dbName . '.village_' . $i);
                foreach (array_chunk($hashVillage[$i], 3000) as $t) {
                    $insert->insert($t);
                }
            }
        }

        $world = new World();
        $world->setTable(env('DB_DATABASE_MAIN') . '.world');
        $worldUpdate = $world->where('name', $worldName)->first();
        $worldUpdate->village_count = count($array);
        $worldUpdate->save();

        echo time() - $time;
    }

    public function latestAlly($worldName){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('max_execution_time', 1800);
        ini_set('max_input_time', 1800);
        ini_set('memory_limit', '200M');
        date_default_timezone_set("Europe/Berlin");
        $dbName = str_replace('{server}{world}', '',env('DB_DATABASE_WORLD')).$worldName;
        if (BasicFunctions::existTable($dbName, 'ally_latest') === false){
            $this->allyTable($dbName, 'latest');
        }
        $time = time();
        $lines = gzfile("https://$worldName.die-staemme.de/map/ally.txt.gz");
        if(!is_array($lines)) die("Datei ally konnte nicht ge&ouml;ffnet werden");

        $allys = collect();
        $allyOffs = collect();
        $allyDefs = collect();
        $allyTots = collect();

        foreach ($lines as $line){
            list($id, $name, $tag, $members, $points, $villages, $rank) = explode(',', $line);
            $ally = collect();
            $ally->put('id', (int)$id);
            $ally->put('name', $name);
            $ally->put('tag', $tag);
            $ally->put('member_count', (int)$members);
            $ally->put('points', (int)$points);
            $ally->put('village_count', (int)$villages);
            $ally->put('rank', (int)$rank);
            $allys->put($ally->get('id'),$ally);
        }

        $offs = gzfile("https://$worldName.die-staemme.de/map/kill_att_tribe.txt.gz");
        if(!is_array($offs)) die("Datei kill_off konnte nicht ge&ouml;ffnet werden");
        foreach ($offs as $off){
            list($rank, $id, $kills) = explode(',', $off);
            $allyOff = collect();
            $allyOff->put('offRank', (int)$rank);
            $allyOff->put('off', (int)$kills);
            $allyOffs->put($id, $allyOff);

        }

        $defs = gzfile("https://$worldName.die-staemme.de/map/kill_def_tribe.txt.gz");
        if(!is_array($defs)) die("Datei kill_def konnte nicht ge&ouml;ffnet werden");
        foreach ($defs as $def){
            list($rank, $id, $kills) = explode(',', $def);
            $allyDef = collect();
            $allyDef->put('defRank', (int)$rank);
            $allyDef->put('def', (int)$kills);
            $allyDefs->put($id, $allyDef);
        }

        $tots = gzfile("https://$worldName.die-staemme.de/map/kill_all_tribe.txt.gz");
        if(!is_array($tots)) die("Datei kill_all konnte nicht ge&ouml;ffnet werden");
        foreach ($tots as $tot){
            list($rank, $id, $kills) = explode(',', $tot);
            $allyTot = collect();
            $allyTot->put('totRank', (int)$rank);
            $allyTot->put('tot', (int)$kills);
            $allyTots->put($id, $allyTot);
        }

        $insert = new Ally();
        $insert->setTable($dbName.'.ally_latest');
        $array = array();
        foreach ($allys as $ally) {
            $data = [
                'allyID' => $ally->get('id'),
                'name' => $ally->get('name'),
                'tag' => $ally->get('tag'),
                'member_count' => $ally->get('member_count'),
                'points' => $ally->get('points'),
                'village_count' => $ally->get('village_count'),
                'rank' => $ally->get('rank'),
                'offBash' => (is_null($allyOffs->get($id)))? null :$allyOffs->get($id)->get('off'),
                'offBashRank' => (is_null($allyOffs->get($id)))? null : $allyOffs->get($id)->get('off'),
                'defBash' => (is_null($allyDefs->get($id)))? null : $allyDefs->get($id)->get('def'),
                'defBashRank' => (is_null($allyDefs->get($id)))? null : $allyDefs->get($id)->get('defRank'),
                'gesBash' => (is_null($allyTots->get($id)))? null : $allyTots->get($id)->get('tot'),
                'gesBashRank' => (is_null($allyTots->get($id)))? null : $allyTots->get($id)->get('totRank'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
            $array []= $data;
        }
        foreach (array_chunk($array,3000) as $t){
            $insert->insert($t);
        }

        $hashAlly = $this->hashTableAlly($allys, $allyOffs, $allyDefs, $allyTots);

        for ($i = 0; $i < env('HASH_ALLY'); $i++){
            if (array_key_exists($i ,$hashAlly)) {
                if (BasicFunctions::existTable($dbName, 'ally_' . $i) === false) {
                    $this->allyTable($dbName, $i);
                }
                $insert->setTable($dbName . '.ally_' . $i);
                foreach (array_chunk($hashAlly[$i], 3000) as $t) {
                    $insert->insert($t);
                }
            }
        }

        $world = new World();
        $world->setTable(env('DB_DATABASE_MAIN').'.world');
        $worldUpdate = $world->where('name', $worldName)->first();
        $worldUpdate->ally_count = count($array);
        $worldUpdate->save();

        echo time()-$time;
    }

    public function hashTablePlayer($mainArrays, $offArray, $defArray, $totArray){
        date_default_timezone_set("Europe/Berlin");
        foreach ($mainArrays as $main){
            $id = $main->get('id');
            $hashArray[BasicFunctions::hash($main->get('id'), 'p')][$id] = [
                'playerID' => $main->get('id'),
                'name' => $main->get('name'),
                'ally_id' => $main->get('ally'),
                'village_count' => $main->get('villages'),
                'points' => $main->get('points'),
                'rank' => $main->get('rank'),
                'offBash' => (is_null($offArray->get($id)))? null :$offArray->get($id)->get('off'),
                'offBashRank' => (is_null($offArray->get($id)))? null : $offArray->get($id)->get('off'),
                'defBash' => (is_null($defArray->get($id)))? null : $defArray->get($id)->get('def'),
                'defBashRank' => (is_null($defArray->get($id)))? null : $defArray->get($id)->get('defRank'),
                'gesBash' => (is_null($totArray->get($id)))? null : $totArray->get($id)->get('tot'),
                'gesBashRank' => (is_null($totArray->get($id)))? null : $totArray->get($id)->get('totRank'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
        }

        return $hashArray;
    }

    public function hashTableAlly($mainArrays, $offArray, $defArray, $totArray){
        date_default_timezone_set("Europe/Berlin");
        foreach ($mainArrays as $main){
            $id = $main->get('id');
            $hashArray[BasicFunctions::hash($main->get('id'), 'a')][$id] = [
                'allyID' => $main->get('id'),
                'name' => $main->get('name'),
                'tag' => $main->get('tag'),
                'member_count' => $main->get('member_count'),
                'points' => $main->get('points'),
                'village_count' => $main->get('village_count'),
                'rank' => $main->get('rank'),
                'offBash' => (is_null($offArray->get($id)))? null :$offArray->get($id)->get('off'),
                'offBashRank' => (is_null($offArray->get($id)))? null : $offArray->get($id)->get('offRank'),
                'defBash' => (is_null($defArray->get($id)))? null : $defArray->get($id)->get('def'),
                'defBashRank' => (is_null($defArray->get($id)))? null : $defArray->get($id)->get('defRank'),
                'gesBash' => (is_null($totArray->get($id)))? null : $totArray->get($id)->get('tot'),
                'gesBashRank' => (is_null($totArray->get($id)))? null : $totArray->get($id)->get('totRank'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
        }

        return $hashArray;
    }

    public function hashTableVillage($mainArrays){
        date_default_timezone_set("Europe/Berlin");
        foreach ($mainArrays as $main){
            $id = $main->get('id');
            $hashArray[BasicFunctions::hash($main->get('id'), 'v')][$id] = [
                'villageID' => $main->get('id'),
                'name' => $main->get('name'),
                'x' => $main->get('x'),
                'y' => $main->get('y'),
                'points' => $main->get('points'),
                'owner' => $main->get('owner'),
                'bonus_id' => $main->get('bonus_id'),
                'created_at' => Carbon::createFromTimestamp(time()),
                'updated_at' => Carbon::createFromTimestamp(time()),
            ];
        }

        return $hashArray;
    }

}