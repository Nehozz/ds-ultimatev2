<?php

namespace App;

use App\Util\BasicFunctions;

class Village extends CustomModel
{
    private $hash = 109;
    protected $primaryKey = 'villageID';
    protected $fillable =[
            'id', 'name', 'x', 'y', 'points', 'owner', 'bonus_id',
    ];
    
    public $timestamps = true;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->hash = env('HASH_VILLAGE', 109);

    }

    /**
     *@return int
     */
    public function getHash(){
        return $this->hash;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function playerLatest()
    {
        $table = explode('.', $this->table);
        return $this->mybelongsTo('App\Player', 'owner', 'playerID', $table[0].'.player_latest');
    }

    /**
     * @param string $server
     * @param $world
     * @param int $village
     * @return $this
     */
    public static function village($server, $world, $village){
        $villageModel = new Village();
        $villageModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.village_latest');

        return $villageModel->find($village);
    }

    /**
     * @param string $server
     * @param $world
     * @param int $villageID
     * @return \Illuminate\Support\Collection
     */
    public static function villageDataChart($server, $world, $villageID){
        $tabelNr = $villageID % env('HASH_VILLAGE');
        $villageModel = new Village();
        $villageModel->setTable(BasicFunctions::getDatabaseName($server, $world).'.village_'.$tabelNr);
        
        $villageDataArray = $villageModel->where('villageID', $villageID)->orderBy('updated_at', 'DESC')->get();
        $villageDatas = collect();

        foreach ($villageDataArray as $village){
            $villageData = collect();
            $villageData->put('timestamp', (int)$village->updated_at->timestamp);
            $villageData->put('points', $village->points);
            $villageDatas->push($villageData);
        }

        return $villageDatas;
    }

    /**
     * @return string
     */
    public function bonusText() {
        switch($this->bonus_id) {
            case 0:
                return "-";
            case 1:
                return "+100% Holz";
            case 2:
                return "+100% Lehm";
            case 3:
                return "+100% Eisen";
            case 4:
                return "+10% Bevölkerung";
            case 5:
                return "+33% schnellere Kaserne";
            case 6:
                return "+33% schnellerer Stall";
            case 7:
                return "+50% schnellere Werkstatt";
            case 8:
                return "+30% auf alle Rohstoffe";
            case 9:
                return "+50% Händler & Speicher";
        }
        return '-';
    }

    /**
     * @return string
     */
    public function continentString() {
        return "K" . intval($this->x / 100) . intval($this->y / 100);
    }

    /**
     * @return string
     */
    public function coordinates() {
        return $this->x."|".$this->y;
    }

    /**
     * @param $skin
     * @return string|null
     */
    public function getVillageSkinImage($skin) {
        $skins = array("dark", "default", "old", "symbol", "winter");
        $index = array_search($skin, $skins);
        if($index === false){
            return null;
        }

        $left = "";
        if($this->owner == 0) {
            $left = "_left";
        }

        if($this->points < 300) {
            $lv = 1;
        } else if($this->points < 1000) {
            $lv = 2;
        } else if($this->points < 3000) {
            $lv = 3;
        } else if($this->points < 9000) {
            $lv = 4;
        } else if($this->points < 11000) {
            $lv = 5;
        } else {
            $lv = 6;
        }

        $bonus = "v";
        if($this->bonus_id != 0) {
            $bonus = "b";
        }

        return "ds_images/skins/{$skins[$index]}/$bonus$lv$left.png";
    }
}
