<?php
namespace app\modules\paypal\controllers;

use Yii;
use yii\web\Controller;

use app\components\PayPal;
use app\modules\paypal\models\Payment;

class DefaultController extends Controller {

    public function actionIndex() {
        $model = new Payment;

        if($model->load(Yii::$app->request->post()) && $model->validate()) {
        	$payment = Yii::$app->payPal->setUpPayment($model->amount, null, $model->currency, $model->description);

            return $this->redirect($payment->checkoutUrl.$payment->token);
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }

    public function actionResult($token) {
    	$result = Yii::$app->payPal->getApprovedPaymentDetails($token);
        $result = $result->getCurlResponse();
        
    	print_r($result['CHECKOUTSTATUS']);
    }
}
