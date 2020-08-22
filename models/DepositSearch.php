<?php

namespace app\models;

use app\common\Common;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use app\models\Deposit;
use yii\db\Query;

/**
 * DepositSearch represents the model behind the search form of `app\models\Deposit`.
 */
class DepositSearch extends Deposit
{
    public $starttime;
    public $endtime;
    public $startmoney;
    public $endmoney;
    public $page;
    public $pageSize;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'deposit_status', 'page', 'pageSize'], 'integer'],
            [['starttime', 'endtime'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            [['system_deposit_id', 'out_deposit_id', 'username', 'deposit_remark', 'system_remark', 'insert_at', 'update_at', 'starttime', 'endtime', 'startmoney', 'endmoney', 'page', 'pageSize'], 'safe'],
            [['deposit_money', 'startmoney', 'endmoney'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return mixed
     */
    public function search($params)
    {
        $query = Deposit::find();

        $username = array($params['username']);
        if (isset($params['include_followers']) && $params['include_followers'] == 1) {
            $followers = Cashier::getFollowers($params['username']);
            $followers = $followers ? array_column($followers, 'username') : array();
            $username = array_unique(array_merge($username, $followers));
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->setAttributes($params);

        if (!$this->validate()) {
            $errors = $this->getFirstErrors();
            return reset($errors);
        }

        $this->page = isset($this->page) && is_numeric($this->page) && $this->page > 0 ? intval($this->page) : 1;
        $this->pageSize = isset($this->pageSize) && is_numeric($this->pageSize) && $this->pageSize > 0 ? intval($this->pageSize) : 10;

        if (isset($this->deposit_status) && $this->deposit_status != null) {
            $query->andWhere(['deposit_status' => $this->deposit_status]);
        }

        $query->andFilterWhere(['like', 'system_deposit_id', $this->system_deposit_id])
            ->andFilterWhere(['like', 'out_deposit_id', $this->out_deposit_id])
            ->andFilterWhere(['in', 'username', $username])
            ->andFilterWhere(['like', 'deposit_remark', $this->deposit_remark])
            ->andFilterWhere(['like', 'system_remark', $this->system_remark])
            ->andFilterWhere(['>=', 'insert_at', $this->starttime])
            ->andFilterWhere(['<=', 'insert_at', $this->endtime])
            ->andFilterWhere(['>=', 'deposit_money', $this->startmoney])
            ->andFilterWhere(['<=', 'deposit_money', $this->endmoney]);

        $totalCount = $dataProvider->totalCount;
        $maxPage = ceil($totalCount / $this->pageSize);
        $this->page = $this->page > $maxPage ? $maxPage : $this->page;

        $limit = $this->pageSize;
        $offset = ($this->page - 1) * $this->pageSize;
        $query->limit($limit)->offset($offset);

        $query->orderBy('insert_at DESC');

        return array(
            'page' => $this->page,
            'pageSize' => $this->pageSize,
            'totalPage' => $maxPage,
            'total' => $totalCount,
            'data' => $query->asArray()->all(),
        );
    }
}
