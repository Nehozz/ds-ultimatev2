<?php

namespace App;

use App\Util\BasicFunctions;
use Illuminate\Database\Eloquent\Model;

class Ally extends Model
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

    public function playerLatest()
    {
        $table = explode('.', $this->table);
        return $this->myhasMany('App\Player', 'ally_id', 'allyID', $table[0].'.player_latest');
    }

    public function myhasMany($related, $foreignKey = null, $localKey = null, $table)
    {
        $instance = $this->newRelatedInstance($related);

        $instance->setTable($table);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany(
            $instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey
        );
    }

    public static function getAllyAll($server, $world){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->orderBy('rank')->get();
    }

    /*
     * Angepasste Funktion für die Ablageart der DB
     * */

    public static function top10Ally($server, $world){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->orderBy('rank')->limit(10)->get();

    }

    public static function ally($server, $world, $ally){
        $allyModel = new Ally();
        $allyModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.ally_latest');

        return $allyModel->find($ally);

    }

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
            // FIXME: no ut bash for ally?
            $allyData->put('utBash', $ally->gesBash-$ally->offBash-$ally->defBash);
            $allyDatas->push($allyData);
        }

        return $allyDatas;

    }

}
