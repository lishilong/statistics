<?php
namespace backend\models\sanhai;
use common\models\pos\ComArea;
use common\models\pos\SeSchoolInfo;
use Yii;
use yii\base\Model;
use yii\caching\FileCache;
use yii\data\ArrayDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;


/**
 * Created by PhpStorm.
 * User: aaa
 * Date: 2016/4/8
 * Time: 10:43
 */
    class Statistical extends Model
    {
        public $provience;
        public $city;
        public $schoolID;
        public $teacher;
        public $thSum;
        public $thNum;
        public $student;
        public $stdSum;
        public $stdNum;
        public $home;
        public $homeSum;
        public $homeNum;
        public $homeworkThSum;
        public $homeworkThCount;
        public $homeworkSbSum;
        public $homeworkSbCount;
        public $homeworkChSum;
        public $homeworkChCount;
        public $schoolNum;

        public function rules(){
            return[
                [['provience','city','schoolID'], 'safe'],
            ];
        }

        public function scenarios()
        {
            return Model::scenarios();
        }

        public function search($params,$startTime,$endTime)
        {
            $cacheKey = "schoolIdArrs_cache";

            $fileCache = new FileCache();
            $schoolArr = $fileCache->get($cacheKey);
            if ($schoolArr === false) {
                $queryschool = new Query();
                $queryschool->select('schoolID')->from('`se_userinfo` GROUP BY schoolID');
                $schoolArr = $queryschool->createCommand(Yii::$app->get('db_school'))->queryColumn();
                $fileCache->set($cacheKey, $schoolArr, 3600*24);
            }
            $query = new Query();
            $query->select('provience,city,schoolID')->from('se_schoolInfo')->where(['schoolId'=>$schoolArr]);
            /**
             * @param  Query $query
             * @return ArrayDataProvider
             * @throws \yii\base\InvalidConfigException
             */
            $dataProvider_fun =function($query,$startTime,$endTime)
          {
              $list = [];
              if(!empty($startTime) && !empty($endTime)) {
                  $startTimes = strtotime(date("Y-m-d 00:00:00", $startTime)) * 1000;
                  $endTimes = strtotime(date("Y-m-d 23:59:59", $endTime)) * 1000;

                  $arrayDate = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
                  $schoolIdArr = \yii\helpers\ArrayHelper::getColumn($arrayDate, 'schoolID');
                  //作业布置使用量
                  $homeworkPeoples = $this->usePeople($startTimes, $endTimes, $schoolIdArr);
                  //学校注册量
                  $schoolPeoples = $this->schoolPeople($startTimes, $endTimes, $schoolIdArr);
                  //老师使用人数
                  $teacherNum = $this->teacherPeople($startTimes, $endTimes);
                  //作业提交
                  $homeworkUsePeoples = $this->homeworkPeople($startTimes, $endTimes, $schoolIdArr);
                  //作业批改使用量
                  $teacherChecks = $this->teacherHomeworkCheck($startTimes, $endTimes, $schoolIdArr);
                  //学校老师，学生激活人数
                  $teacherStudentSum = $this->teacherStudentStatistivalSum($startTimes,$endTimes,$schoolIdArr);
                  //家长激活人数
                  $homeSum = $this->parentsSum($startTimes,$endTimes,$schoolIdArr);
                  $homeworkfunction = function (&$homeworkPeople, $schoolId) {
                      foreach ($homeworkPeople as $key => $item) {
                          if ($item['schoolID'] == $schoolId) {
                              unset($homeworkPeople[$key]);
                              return $item;
                          }
                      }
                      return null;
                  };



                  foreach ($arrayDate as $val) {
                      $homeworkPeople = $homeworkfunction($homeworkPeoples, $val['schoolID']);
                      $schoolPeople = $homeworkfunction($schoolPeoples, $val['schoolID']);
                      $homeworkUsePeople = $homeworkfunction($homeworkUsePeoples, $val['schoolID']);
                      $studentTeacher = $homeworkfunction($teacherStudentSum, $val['schoolID']);
                      $homeList = $homeworkfunction($homeSum, $val['schoolID']);
                      $teacherCheck = $homeworkfunction($teacherChecks, $val['schoolID']);
                      $arr['provience'] = '';
                      $arr['city'] = '';
                      $arr['schoolID'] = '';
                      $arr['teacher'] = 0;
                      $arr['student'] = 0;
                      $arr['schoolNum'] = 0;
                      $arr['home'] = 0;
                      $arr['homeworkThSum'] = 0;
                      $arr['homeworkThCount'] = 0;
                      $arr['homeworkSbCount'] = 0;
                      $arr['homeworkSbSum'] = 0;
                      $arr['homeworkChCount'] = 0;
                      $arr['homeworkChSum'] = 0;
                      $arr['stdSum'] = 0;
                      $arr['stdNum'] = 0;
                      $arr['thSum'] = 0;
                      $arr['thNum'] = 0;

                      $arr['provience'] = $val['provience'];
                      $arr['city'] = $val['city'];
                      $arr['schoolID'] = $val['schoolID'];
                      //老师使用量
                      if (!empty($teacherNum)) {
                          foreach ($teacherNum as $key => $v) {
                              if($v['schoolID'] == $val['schoolID'])
                              {
                                  $arr['thSum'] = $v['creator'];
                                  break;
                              }
                          }
                      }

                      //添加激活量
                      if (!empty($schoolPeople)) {

                          $arr['schoolNum'] = $schoolPeople['schoolNum'];
                      }
                      //家长激活人数
                      if(!empty($homeList))
                      {
                          $arr['home'] = $homeList['home'];
                      }
                      //学生和老师激活人数
                      if(!empty($studentTeacher)){
                          $arr['teacher'] = $studentTeacher['teacher'];
                          $arr['student'] = $studentTeacher['student'];
                      }
                      //添加布置量
                      if (!empty($homeworkPeople)) {
                          $arr['homeworkThSum'] = $homeworkPeople['creatorNum'];
                          $arr['homeworkThCount'] = $homeworkPeople['memberTotal'];
                      }
                      // 提交量
                      if (!empty($homeworkUsePeople)) {
                          $arr['homeworkSbCount'] = $homeworkUsePeople['studentNum'];
                          $arr['homeworkSbSum'] = $homeworkUsePeople['studentSum'];
                          $arr['stdSum'] = $homeworkUsePeople['studentSum'];
                          $arr['stdNum'] = $homeworkUsePeople['studentNum'];
                      }
                      //批改量
                      if(!empty($teacherCheck))
                      {
                          $arr['homeworkChCount'] = $teacherCheck['checkNum'];
                          $arr['homeworkChSum'] = $teacherCheck['checkSum'];
                          $arr['thNum'] = floatval($arr['homeworkThCount']) + floatval($teacherCheck['checkNum']);
                      }
                      if ($arr['teacher'] == 0 && $arr['student'] == 0 && $arr['schoolNum'] == 0 && $arr['home'] == 0 && $arr['homeworkThSum'] == 0 &&
                          $arr['homeworkThCount'] == 0 && $arr['homeworkSbCount'] == 0 && $arr['homeworkSbSum'] == 0 && $arr['homeworkChCount'] == 0 &&
                          $arr['homeworkChSum'] == 0 && $arr['stdSum'] == 0 && $arr['stdNum'] == 0 && $arr['thSum'] == 0 && $arr['thNum'] == 0
                      ) {
                          continue;
                      }

                      $list[] = $arr;
                  }
              }
            return  new ArrayDataProvider([
                  'allModels' => $list,
                  'sort' => [
                      'attributes' => ['provience', 'city', 'schoolID', 'teacher', 'student', 'home',
                                        'thSum','thNum','stdSum','stdNum','homeSum',
                                        'homeNum','homeworkChSum','homeworkChCount','homeworkSbSum','homeworkSbCount',
                                        'homeworkThSum','homeworkThCount' ,'schoolNum' ],
                  ],
                  'pagination' => [
                      'pageSize' =>50,
                  ],
              ]);
          };

            $this->load($params);

            if (!$this->validate()) {
                return  $dataProvider_fun($query,$startTime,$endTime);
            }
            if(!empty($this->provience)){
                $provienceId = ComArea::find()->where(['like','AreaName',$this->provience])->select('AreaID')->column();
                $query->andWhere(['provience'=>$provienceId]);
            }
            if(!empty($this->city)){
                $cityId = ComArea::find()->where(['like','AreaName',$this->city])->select('AreaID')->column();
                $query->andWhere(['city'=>$cityId]);
            }
            if(!empty($this->schoolID)){
                $schoolID = SeSchoolInfo::find()->where(['like','schoolName',$this->schoolID])->select('schoolID')->column();
                $query->andWhere(['schoolID'=>$schoolID]);
            }
            return   $dataProvider_fun($query,$startTime,$endTime);
        }



   /*作业布置量
    * $startTime 开始时间
    * $endTime   结束时间
    * $schoolID  学校ID
    * return
     */
        public function usePeople($startTimes,$endTimes,$schoolID){
            $queryModel = new Query();
            $queryModel->select('m.schoolID,m.creatorNum creatorNum,m.memberTotal memberTotal')
                         ->from("(SELECT l.schoolID,SUM(l.memberTotal) memberTotal,COUNT(DISTINCT(l.creator)) creatorNum FROM
                              (SELECT id,creator,memberTotal,(SELECT schoolID FROM se_userinfo WHERE userID = s.creator) schoolID
                              FROM se_homework_rel s  where createTime between :startTimes and :endTimes) l GROUP BY l.schoolID) m");
            $queryModel->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $queryModel->andWhere(['m.schoolID' => $schoolID]);
            $arrayList = $queryModel->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $arrayList;
        }


        /*
         * 作业提交
         */
        public function homeworkPeople($startTimes,$endTimes,$schoolID)
        {
            $query = new Query();
            $query->select('f.schoolID,f.studentNum,f.studentSum')
                ->from('(SELECT u.schoolID,count(DISTINCT(i.studentID)) studentSum,COUNT(i.studentID) studentNum
                                  FROM (select * from se_homeworkAnswerInfo where isUploadAnswer = 1 and uploadTime between :startTimes and :endTimes)
                                  i LEFT JOIN se_userinfo u ON u.userID = i.studentID
                                  GROUP BY u.schoolID) f');
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $query->andWhere(['f.schoolID' => $schoolID]);
            $arrayList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $arrayList;
        }




        /*
         * 作业批改量
         */
        public function teacherHomeworkCheck($startTimes,$endTimes,$schoolID)
        {
            $query = new Query();
            $query->select('x.schoolID,x.checkNum,x.checkSum')
                ->from('(SELECT u.schoolID,COUNT(case when i.isCheck=1 then 1  ELSE NULL end) checkNum,
                                  count(DISTINCT (CASE WHEN i.isCheck = 1 THEN i.studentID	ELSE NULL END)) checkSum
                                  FROM (select * from se_homeworkAnswerInfo where isUploadAnswer =1 and checkTime between :startTimes and :endTimes)
                                  i LEFT JOIN se_userinfo u ON u.userID = i.studentID
                                  GROUP BY u.schoolID) x');
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $query->andWhere(['x.schoolID' => $schoolID]);
            $arrayList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $arrayList;
        }


        /*
         * 家长激活人数
         */
        public function parentsSum($startTimes,$endTimes,$schoolID)
        {
            $query = new Query();
            $query->select('count(0) home, c.schoolID')
                  ->from('(select bindphone, trueName,(select userID from se_userinfo u where u.phone=p.phoneReg and type=0 limit 1) childuserID
                      from se_userinfo p where type =3 and status1=1 and createTime between :startTimes and :endTimes) a ')
                 ->leftJoin('se_userinfo c','c.userID = a.childuserID')
                ->groupBy('c.schoolID');
            $query->andWhere(['c.schoolID'=>$schoolID]);
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $homeSum = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $homeSum;
        }


        /*
         * 学校的注册人数
         */
        public function schoolPeople($startTimes,$endTimes,$schoolID)
        {
            $query = new Query();
            $query->select('t.schoolID,t.school schoolNum')
                ->from('(select schoolID,COUNT(*) school from  se_userinfo where createTime between :startTimes and :endTimes GROUP BY schoolID) t');
            $query->andWhere(['t.schoolID'=>$schoolID]);
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $schoolList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $schoolList;
        }



        /*
         * 学校老师，学生的激活人数
         */
        public function teacherStudentStatistivalSum($startTimes,$endTimes,$schoolID)
        {
            $query = new Query();
            $query->select('u.schoolID,count(case when u.type=1 then 1 end ) teacher,count(case when u.type=0 then 0 end ) student')
                  ->from('(select userID,schoolID,type from se_userinfo where userID in (select distinct(userID) from  se_userControl where firstTime between :startTimes and :endTimes)) u')
                  ->groupBy('u.schoolID');
            $query->andWhere(['u.schoolID'=>$schoolID]);
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $statistivalSum = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $statistivalSum;
        }



        /*
         * 老师使用人数
         */
        public function teacherPeople($startTimes,$endTimes)
        {
            $query = new Query();
            $query->select('t.schoolID,count(*) creator')
                  ->from('
              ( SELECT
			*
		FROM
			(
				SELECT
					rel.creator,
					u.schoolID
				FROM
					se_homework_rel rel
				JOIN se_userinfo u ON rel.creator = u.userId
				WHERE
					rel.id IN (
						SELECT
							relId
						FROM
							se_homeworkAnswerInfo s
						WHERE
						  isUploadAnswer = 1
						AND
							uploadTime BETWEEN :startTimes and :endTimes
						AND isCheck = 1
						AND checkTime > 0
					)
			) p1
		UNION
			SELECT
				*
			FROM
				(
					SELECT
						rel.creator,
						`u`.`schoolID`
					FROM
						se_homework_rel rel
					JOIN se_userinfo u ON `rel`.`creator` = `u`.`userId`
					WHERE
						`rel`.`createTime` BETWEEN :startTimes and :endTimes
				) p2) t')
                  ->groupBy('t.schoolID');
            $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
            $teacherSum = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
            return $teacherSum;
        }



    }