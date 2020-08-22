<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\PayChannel;

/**
 * PayChannelSearch represents the model behind the search form of `app\models\PayChannel`.
 */
class PayChannelSearch extends PayChannel
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'pay_type', 'channel_status'], 'integer'],
            [['channel_name', 'user_level', 'credit_level', 'update_at', 'insert_at'], 'safe'],
            [['per_max_amount', 'per_min_amount'], 'number'],
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
        $query = PayChannel::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'pay_type' => $this->pay_type,
            'per_max_amount' => $this->per_max_amount,
            'per_min_amount' => $this->per_min_amount,
            'channel_status' => $this->channel_status,
            'update_at' => $this->update_at,
            'insert_at' => $this->insert_at,
        ]);

        $query->andFilterWhere(['like', 'channel_name', $this->channel_name])
            ->andFilterWhere(['like', 'user_level', $this->user_level])
            ->andFilterWhere(['like', 'credit_level', $this->credit_level]);

        return $dataProvider;
    }
}
