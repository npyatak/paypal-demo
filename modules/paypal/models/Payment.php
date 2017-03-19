<?php

namespace app\modules\paypal\models;

use Yii;
use yii\base\Model;

class Payment extends Model {

    public $amount;
    public $currency;
    public $description;

    public function rules()
    {
        return [
            [['amount', 'currency'], 'required'],
            ['currency', 'in', 'range' => Yii::$app->payPal->supportedCurencies],
            ['description', 'string'],
        ];
    }

    public function getCurrenciesArray() {
        $result = [];
        foreach (Yii::$app->payPal->supportedCurencies as $currency) {
            $result[$currency] = $currency;
        }

        return $result;
    }
}
