<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Withdraw;

/**
 * WithdrawSearch represents the model behind the search form of `app\models\Withdraw`.
 */
class WithdrawSearch extends Withdraw
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
            [['id', 'user_type', 'bankcard_id', 'withdraw_status', 'page', 'pageSize'], 'integer'],
            [['starttime', 'endtime'], 'date', 'format'=>'php:Y-m-d H:i:s'],
            [['system_withdraw_id', 'out_withdraw_id', 'username', 'withdraw_remark', 'system_remark', 'insert_at', 'update_at', 'starttime', 'endtime', 'startmoney','endmoney', 'page', 'pageSize'], 'safe'],
            [['withdraw_money', 'startmoney', 'endmoney'], 'number'],
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
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Withdraw::find()->select('withdraw.*, user_bankcard.bankcard_number')
            ->leftJoin('user_bankcard', 'withdraw.bankcard_id = user_bankcard.id');

        //是否包含下级只应用于提款员
        $username = array($params['username']);
        if(isset($params['user_type']) && $params['user_type'] == Withdraw::$UserTypeCashier && isset($params['include_followers']) && $params['include_followers'] == 1){
            $followers = Cashier::getFollowers($params['username']);
            $followers = $followers ? array_column($followers, 'username') : array();
            $username = array_unique(array_merge($username,$followers));
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

        $query->andFilterWhere([
            'withdraw_status' => $this->withdraw_status,
            'withdraw.user_type' => $this->user_type,
        ]);

        $query->andFilterWhere(['like', 'system_withdraw_id', $this->system_withdraw_id])
            ->andFilterWhere(['like', 'out_withdraw_id', $this->out_withdraw_id])
            ->andFilterWhere(['in', 'withdraw.username', $username])
            ->andFilterWhere(['like', 'withdraw_remark', $this->withdraw_remark])
            ->andFilterWhere(['like', 'system_remark', $this->system_remark])
            ->andFilterWhere(['>=', 'withdraw.insert_at', $this->starttime])
            ->andFilterWhere(['<=', 'withdraw.insert_at', $this->endtime])
            ->andFilterWhere(['>=', 'withdraw_money', $this->startmoney])
            ->andFilterWhere(['<=', 'withdraw_money', $this->endmoney]);

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
            'data'=> $query->asArray()->all(),
        );
    }
}
