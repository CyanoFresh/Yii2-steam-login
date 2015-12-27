<?php
namespace frontend\controllers;

use common\models\User;
use nodge\eauth\ErrorException;
use nodge\eauth\openid\ControllerBehavior;
use Yii;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\base\InvalidParamException;
use yii\helpers\VarDumper;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
            'eauth' => [
                'class' => ControllerBehavior::className(),
                'only' => ['login'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        /** @var $eauth \nodge\eauth\ServiceBase */
        $eauth = Yii::$app->get('eauth')->getIdentity('steam');

        $eauth->setRedirectUrl(Yii::$app->getUser()->getReturnUrl());
        $eauth->setCancelUrl(Yii::$app->getUrlManager()->createAbsoluteUrl('site/login'));

        try {
            if ($eauth->authenticate()) {
                $identity = User::findByEAuth($eauth);

                $user = User::findOne(['steamid' => $identity->steamid]);

                if (!$user) {
                    $user = new User();
                }

                $user->username = $identity->username;
                $user->steamid = $identity->steamid;
                $user->profile_url = $identity->profile_url;
                $user->avatar = $identity->avatar;
                $user->avatar_md = $identity->avatar_md;
                $user->avatar_lg = $identity->avatar_lg;
                $user->generateAuthKey();

                $user->save();

                Yii::$app->getUser()->login($identity);

                $eauth->redirect();
            } else {
                $eauth->cancel();
            }
        } catch (ErrorException $e) {
            Yii::$app->getSession()->setFlash('error', 'EAuthException: ' . $e->getMessage());

            $eauth->redirect($eauth->getCancelUrl());
        }

//        if (!\Yii::$app->user->isGuest) {
//            return $this->goHome();
//        }
//
//        $model = new LoginForm();
//        if ($model->load(Yii::$app->request->post()) && $model->login()) {
//            return $this->goBack();
//        } else {
//            return $this->render('login', [
//                'model' => $model,
//            ]);
//        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
