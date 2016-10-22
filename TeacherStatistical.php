<?php
namespace backend\models\sanhai;
use common\models\pos\ComArea;
use common\models\pos\SeClass;
use common\models\pos\SeSchoolInfo;
use common\models\pos\SeUserinfo;
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
 * Date: 2016/7/27
 * Time: 15:45
 */
   class TeacherStatistical extends Model
   {
       public $provience;
       public $city;
       public $schoolID;
       public $userID;
       public $phone;
       public $gradeID;
       public $classID;
       public $subjectID;
       public $homeworkNum;
       public $homeworkSbNum;
       public $homeworkThNum;
       public $homeworkThCount;
       public $platformCount;

       public function rules(){
           return[

           ];
       }

       public function scenarios()
       {
           return Model::scenarios();
       }


       public function search($params,$startTime,$endTime)
       {

           $query = new Query();
           $query->select(' `m` . `classID`,
    `m` . `creator`,
    `se_class`.classID,
    `se_class`.gradeID,
    `sc`.schoolID,
    `sc`.provience,
    `sc`.city')
               ->from(
                   "
                   (select creator,classID from (select creator,classID from se_homework_rel h where createTime between :startTimes and :endTimes
                     GROUP BY `h`.`classID`,`h`.`creator`) p1
                     UNION
                     select creator,classID from (select creator,classID from
                     (select studentID,relId,isCheck from se_homeworkAnswerInfo where isUploadAnswer = 1 and uploadTime between :startTimes and :endTimes) h
                     JOIN se_homework_rel r on `r`.`id` = `h`.`relId` GROUP BY `r`.`classID`,`r`.`creator`) p2 ) m
                     join   `se_class`  on  `m`.classID= `se_class`.classID  join  se_schoolInfo `sc`  on  se_class.schoolID=`sc`.schoolID

                     "
               );




           /**
            * @param  Query $query
            * @return ArrayDataProvider
            * @throws \yii\base\InvalidConfigException
            */
           $rest = function($query,$startTime,$endTime){
               $list = [];
               if(!empty($startTime) && !empty($endTime)){
                   $startTimes = strtotime(date("Y-m-d 00:00:00", $startTime)) * 1000;
                   $endTimes = strtotime(date("Y-m-d 23:59:59", $endTime)) * 1000;

                   $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
                   $query->limit(20000);
                   $class_userArray = $query->createCommand(Yii::$app->get('db_school'))->queryAll();



                   //班级列表
                   $classList = $this->classList($startTimes,$endTimes);
                   $userId = ArrayHelper::getColumn($class_userArray,'creator');
                   //用户信息
                   $userArray = SeUserinfo::find()->where(['userID' => $userId])
                                     ->select('userID,bindphone,subjectID')->limit(10000)->all();

                   //作业提交次数
                   $homeworkSbNum = $this->homeworkSbNum($startTimes,$endTimes);
                   //作业批改次数
                   $homeworkThNum = $this->homeworkThNum($startTimes,$endTimes);
                   //用户信息



                   $userList = function($userArray,$userId)
                   {
                       $array = [];
                       foreach($userArray as $val)
                       {
                           if($val->userID == $userId)
                           {
                              $array = ['bindphone' => $val->bindphone,'subjectID' => $val->subjectID,'userID' => $val->userID];
                              return $array;
                           }
                       }
                       return $array;
                   };

                   //提交次数
                   $studentNumList = function($homeworkSbNum,$userId,$classId)
                   {
                       $array = [];
                       foreach($homeworkSbNum as $val){
                           if($val['creator'] == $userId && $val['classID'] == $classId)
                           {
                               return $val['num'];
                           }
                       }
                       return $array;
                   };


                   //批改,次数和人数
                   $teacherNumList = function($homeworkThNum,$userId,$classId)
                   {
                       $array = [];
                       foreach($homeworkThNum as $val){
                           if($val['creator'] == $userId && $val['classID'] == $classId)
                           {
                               $array = ['num' => $val['num'],'count' => $val['count']];
                               return $array;
                           }
                       }
                       return $array;
                   };

                   //布置列表
                   $classDataList = function($classList,$userId,$classId)
                   {
                       $array = [];
                       foreach($classList as $val){
                           if($val['creator'] == $userId && $val['classID'] == $classId)
                           {
                               $array = ['creator' => $val['creator'],'classID' => $val['classID'],
                                         'gradeID' => $val['gradeID'],'count' => $val['count'],'platformCount' => $val['platformCount']];
                               return $array;
                           }
                       }
                       return $array;
                   };


                   foreach($class_userArray as $val)
                   {

                       $user = $userList($userArray,$val['creator']);
                       $classData = $classDataList($classList,$val['creator'],$val['classID']);
                       $studentNum = $studentNumList($homeworkSbNum,$val['creator'],$val['classID']);
                       $teacherNum = $teacherNumList($homeworkThNum,$val['creator'],$val['classID']);
                       $attr['provience'] = $val['provience'];
                       $attr['city'] = $val['city'];
                       $attr['schoolID'] =$val['schoolID'];
                       $attr['userID'] = $val['creator'];
                       $attr['phone'] = '';
                       $attr['gradeID'] = $val['gradeID'];
                       $attr['classID'] = $val['classID'];
                       $attr['subjectID'] = '';
                       $attr['homeworkNum'] = 0;
                       $attr['homeworkSbNum'] = 0;
                       $attr['homeworkThNum'] = 0;
                       $attr['homeworkThCount'] = 0;
                       $attr['platformCount'] = 0;

                       if(!empty($classData))
                       {
                           $attr['homeworkNum'] = $classData['count'];
                           $attr['platformCount'] = $classData['platformCount'];
                       }

                       if(!empty($user))
                       {
                           $attr['userID'] = $user['userID'];
                           $attr['phone'] = $user['bindphone'];
                           $attr['subjectID'] = $user['subjectID'];
                       }

                       if(!empty($studentNum))
                       {
                           $attr['homeworkSbNum'] = $studentNum;
                       }

                       if(!empty($teacherNum))
                       {
                           $attr['homeworkThNum'] = $teacherNum['num'];
                           $attr['homeworkThCount'] = $teacherNum['count'];
                       }

                       if($attr['homeworkNum'] == 0 &&   $attr['homeworkSbNum'] == 0 &&
                           $attr['homeworkThNum'] == 0 && $attr['homeworkThCount'] == 0)
                       {
                           continue;
                       }
                       $list[] = $attr;

                   }
               }


               return  new ArrayDataProvider([
                   'allModels' => $list,
                   'sort' => [
                       'attributes' => ['provience', 'city', 'schoolID', 'userID', 'phone','gradeID','classID',
                                         'subjectID','homeworkNum','homeworkSbNum','homeworkThNum','homeworkThCount','platformCount'],
                   ],
                   'pagination' => [
                       'pageSize' =>50,
                   ],
               ]);
           };

           $this->load($params);
           if (!$this->validate()) {
               return  $rest($query,$startTime,$endTime);
           }




           if(!empty($this->userID))
           {
               $userID = SeUserinfo::find()->where(['like','trueName',trim($this->userID)])->select('userID')->column();
               $query->andWhere(['userID' => $userID]);
           }
           return $rest($query,$startTime,$endTime);
       }



       /*
        * 用户列表
        * @param  array $classIds 学校ID
        * @param  string $startTimes 开始时间
        * @param  string $endTimes 结束时间
        * @return ArrayDataProvider
        */
       public function  classList($startTimes, $endTimes)
       {
           $query = new Query();
           $query->select('m.creator,m.classID,m.count,m.gradeID,m.platformCount')
                 ->from('( select creator,classID,count(*) count, count((select id from se_homework_teacher t
                  where t.homeworkPlatformId>0 and h.homeworkId=t.id) ) platformCount,
                 (select gradeID from se_class s where s.classID = h.classID) gradeID
                       from se_homework_rel h where createTime between :startTimes and :endTimes
                       group by  `classID`,`creator` )  m ');

           $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
           $query->limit(10000);
           $arrayList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
           return $arrayList;
       }



       /*
       * 作业提交次数
        * @param  string $startTimes 开始时间
        * @param  string $endTimes 结束时间
        * @return ArrayDataProvider
        */
       public function homeworkSbNum($startTimes,$endTimes)
       {
           $query = new Query();
           $query->select('m.classID,m.creator,m.num')
               ->from("(select r.classID,r.creator,count(h.studentID) num from
                    (select studentID,relId from se_homeworkAnswerInfo where isUploadAnswer = 1 and uploadTime between :startTimes and :endTimes) h
                    left JOIN se_homework_rel r on `r`.`id` = `h`.`relId` GROUP BY `r`.`classID`,`r`.`creator`) m");
           $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
           $query->limit(10000);
           $arrayList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
           return $arrayList;
       }



       /*
        * 作业批改次数
        *  @param  string $startTimes 开始时间
        * @param  string $endTimes 结束时间
        * @return ArrayDataProvider
        */
       public function homeworkThNum($startTimes,$endTimes)
       {
           $query = new Query();
           $query->select('m.classID,m.creator,m.num,m.count')
               ->from("(select r.classID,r.creator,count(case when h.isCheck=1 then 1  ELSE NULL end) num,
                     count(DISTINCT (CASE WHEN h.isCheck = 1 THEN h.studentID	ELSE NULL END)) count from
                    (select studentID,relId,isCheck from se_homeworkAnswerInfo where  checkTime between :startTimes and :endTimes) h
                    left JOIN se_homework_rel r on `r`.`id` = `h`.`relId` GROUP BY `r`.`classID`,`r`.`creator`) m");
           $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
           $query->limit(10000);
           $arrayList = $query->createCommand(Yii::$app->get('db_school'))->queryAll();
           return $arrayList;
       }

   }