<?php
namespace console\controllers;
use common\models\statistics\StudentStatistics;
use Yii;
use yii\console\Controller;
use yii\db\Query;

/**
 * Created by PhpStorm.
 * User: aaa
 * Date: 2016/10/21
 * Time: 15:14
 */
 class StudentStatisticsController extends Controller
 {

     /**
      *学生批改作业统计记录表
      */
     public function actionIndex()
     {
         //前一天
         $time = strtotime(date("Y-m-d",strtotime("-1 day")));
         $startTimes = strtotime(date("Y-m-d 00:00:00",$time)) * 1000;
         $endTimes = strtotime(date("Y-m-d 23:59:59", $time)) * 1000;
         $createTime = time()*1000;

         $query = new Query();
         $query->select('s.schoolID,s.provience,s.city,s.country,c.gradeID,c.classID,h.studentID,h.homeworkCount,h.checkTime')
               ->from("(SELECT studentID,COUNT(studentID) homeworkCount,checkTime FROM `se_homeworkAnswerInfo` WHERE isCheck=1
                                        and checkTime between :startTimes and :endTimes  GROUP BY studentID) h
                                        LEFT JOIN `se_classMembers` m on m.`userID` = h.`studentID`
                                        LEFT JOIN `se_class` c on c.`classID` = m.`classID`
                                        LEFT JOIN `se_schoolInfo` s on s.`schoolID` = c.`schoolID`");
         $query->addParams(['startTimes'=>$startTimes,'endTimes'=>$endTimes]);
         $studentData = $query->createCommand(Yii::$app->get('db_school'))->queryAll();

            if(!empty($studentData))
            {
                $studentArray = array_chunk($studentData,100);
                foreach($studentArray as $item)
                {
                    $list = [];
                    foreach($item as $val)
                    {
                        $list[] = [$val['studentID'],$val['schoolID'],$val['gradeID'],$val['classID'],$val['homeworkCount'],
                            $val['provience'],$val['city'],$val['country'],$val['checkTime'],$createTime];
                    }

                    StudentStatistics::getDb()->createCommand()->batchInsert('student_statistics',
                        ['userID','schoolID','gradeID','classID','count','provience',
                            'city','country','checkTime','createTime'],$list)->execute();
                }
            }

     }


 }