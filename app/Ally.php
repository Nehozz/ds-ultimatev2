<?php

namespace App;

use App\Util\BasicFunctions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Ally extends CustomModel
{
    private $hash = 29;
    protected $primaryKey = 'allyID';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->hash = env('HASH_ALLY', 29);
    }

    /**
     *@return int
     */
    public function getHash(){
        return $this->hash;
    }

    /**
     * @return Player
     */
    public function playerLatest()
    {
        $table = explode('.', $this->table);
        return $this->myhasMany('App\Player', 'ally_id', 'allyID', $table[0].'.ally_changes');
    }

    /**
     * @return AllyChanges
     */
    public function allyChangesOld()
    {
        $table = explode('.', $this->table);
        return $this->myhasMany('App\AllyChanges', 'old_ally_id', 'allyID', $table[0].'.ally_changes');
    }

    /**
     * @return AllyChanges
     */
    public function allyChangesNew()
    {
        $table = explode('.', $this->table);
        return $this->myhasMany('App\AllyChanges', 'new_ally_id', 'allyID', $table[0].'.ally_changes');
    }

    /**
     * Gibt alle Stämme einer Welt zurück.
     *
     * @param string $server
     * @param $world
     * @return Collection
     */
    public static function getAllyAll($server, $world){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->orderBy('rank')->get();
    }

    /**
     * Gibt die besten 10 Stämme zurück
     *
     * @param string $server
     * @param $world
     * @return Collection
     */
    public static function top10Ally($server, $world){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->orderBy('rank')->limit(10)->get();

    }

    /**
     * @param string $server
     * @param $world
     * @param  int $ally
     * @return $this
     */
    public static function ally($server, $world, $ally){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->find($ally);

    }

    /**
     * @param string $server
     * @param $world
     * @param int $allyID
     * @return \Illuminate\Support\Collection
     */
    public static function allyDataChart($server, $world, $allyID){
        $tabelNr = $allyID % env('HASH_ALLY');

        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_'.$tabelNr);

        $allyDataArray = $allyModel->where('allyID', $allyID)->orderBy('updated_at', 'DESC')->get();

        $allyDatas = collect();

        foreach ($allyDataArray as $ally){
            $allyData = collect();
            $allyData->put('timestamp', (int)$ally->updated_at->timestamp);
            $allyData->put('points', $ally->points);
            $allyData->put('rank', $ally->rank);
            $allyData->put('village', $ally->village_count);
            $allyData->put('gesBash', $ally->gesBash);
            $allyData->put('offBash', $ally->offBash);
            $allyData->put('defBash', $ally->defBash);
            $allyDatas->push($allyData);
        }

        return $allyDatas;

    }

}
