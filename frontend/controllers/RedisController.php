<?php
/**
 * Created by PhpStorm.
 * User: michaeldu
 * Date: 5/26/15
 * Time: 8:07 AM
 */

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use frontend\models\SignupForm;
use yii\filters\AccessControl;


class RedisController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'signup',
                ],
            ],
        ];
    }

    public function actionSignup()
    {
        $model = new SignupForm();
        if (Yii::$ap->request->isPost) {

            $email = Yii::$app->request->post('email', null);
            $password = Yii::$app->request->post('password', null);
            $username = Yii::$app->request->post('username', null);
            if (in_array(null, [$email, $password, $username])) {
                echo "参数不正确";
                exit;
            }

            //检查邮箱是否唯一
            if ( Yii::$app->redis->hexists("email.to.id", $email) ) {
                echo "该邮箱已被注册";
                exit;
            }

            $userID = Yii::$app->redis->incr("users:count");
            Yii::$app->redis->hmset("user:{$userID}", "email", $email, "password", $password, "username", $username);
            Yii::$app->redis->hset("email.to.id", $email, $userID);

            echo "注册成功";
            exit;
//            if ($user = $model->signup()) {
//                if (Yii::$app->getUser()->login($user)) {
//                    return $this->goHome();
//                }
//            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }
}