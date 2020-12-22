<?php

namespace App\Model\HumanResource\Kpi;

use App\Model\HumanResource\Employee\Employee;
use App\Model\Master\User;
use App\Model\Plugin\PinPoint\SalesVisitation;
use App\Model\Plugin\PinPoint\SalesVisitationTarget;
use App\Model\TransactionModel;
use DateInterval;
use DatePeriod;
use DateTime;

class Automated extends TransactionModel
{
    protected $connection = 'tenant';

    public static $alias = 'automated';

    /**
     * Get the automated data based on indicator.
     */
    public static function getData($automated_code, $dateFrom, $dateTo, $employeeId)
    {
        $dateFrom = convert_to_server_timezone(date('Y-m-d H:i:s', strtotime($dateFrom)));
        $dateTo = convert_to_server_timezone(date('Y-m-d H:i:s', strtotime($dateTo)));

        $employee = Employee::findOrFail($employeeId);
        $userId = $employee->user_id ?? 0;

        $target = 0;
        $score = 0;

        $numberOfDays = self::getDays($dateFrom, $dateTo);

        if ($automated_code === 'C') {
            $queryTarget = SalesVisitationTarget::target($dateFrom, $dateTo);
            $queryCall = SalesVisitation::call($dateFrom, $dateTo);

            $result = User::leftJoinSub($queryTarget, 'queryTarget', function ($join) {
                $join->on('users.id', '=', 'queryTarget.user_id');
            })->leftJoinSub($queryCall, 'queryCall', function ($join) {
                $join->on('users.id', '=', 'queryCall.created_by');
            })->select('users.id')
                ->addSelect('queryTarget.call as target')
                ->addSelect('queryCall.total as score')
                ->where('queryTarget.call', '>', 0)
                ->groupBy('users.id')
                ->get();

            foreach ($result as $user) {
                if ($userId === $user->id) {
                    $target = ($user->target ?? 0) * $numberOfDays;
                    $score = $user->score ?? 0;
                    break;
                }
            }
        } elseif ($automated_code === 'EC') {
            $queryTarget = SalesVisitationTarget::target($dateFrom, $dateTo);
            $queryEffectiveCall = SalesVisitation::effectiveCall($dateFrom, $dateTo);

            $result = User::leftJoinSub($queryTarget, 'queryTarget', function ($join) {
                $join->on('users.id', '=', 'queryTarget.user_id');
            })->leftJoinSub($queryEffectiveCall, 'queryEffectiveCall', function ($join) {
                $join->on('users.id', '=', 'queryEffectiveCall.created_by');
            })->select('users.id')
                ->addSelect('queryTarget.effective_call as target')
                ->addSelect('queryEffectiveCall.total as score')
                ->where('queryTarget.call', '>', 0)
                ->groupBy('users.id')
                ->get();

            foreach ($result as $user) {
                if ($userId === $user->id) {
                    $target = ($user->target ?? 0) * $numberOfDays;
                    $score = $user->score ?? 0;
                    break;
                }
            }
        } elseif ($automated_code === 'V') {
            $queryTarget = SalesVisitationTarget::target($dateFrom, $dateTo);
            $queryValue = SalesVisitation::value($dateFrom, $dateTo);

            $result = User::leftJoinSub($queryTarget, 'queryTarget', function ($join) {
                $join->on('users.id', '=', 'queryTarget.user_id');
            })->leftJoinSub($queryValue, 'queryValue', function ($join) {
                $join->on('users.id', '=', 'queryValue.created_by');
            })->select('users.id')
                ->addSelect('queryTarget.value as target')
                ->addSelect('queryValue.value as score')
                ->where('queryTarget.call', '>', 0)
                ->groupBy('users.id')
                ->get();

            foreach ($result as $user) {
                if ($userId === $user->id) {
                    $target = ($user->target ?? 0) * $numberOfDays;
                    $score = $user->score ?? 0;
                    break;
                }
            }
        }

        return ['score' => $score, 'target' => $target];
    }

    public static function getDays($dateFrom, $dateTo)
    {
        $dateFrom = convert_to_local_timezone(date('Y-m-d H:i:s', strtotime($dateFrom)));
        $dateTo = convert_to_local_timezone(date('Y-m-d H:i:s', strtotime($dateTo)));

        $dateFrom = new DateTime($dateFrom);
        $dateTo = new DateTime($dateTo);

        $interval = $dateTo->diff($dateFrom);

        $numberOfDays = $interval->days;

        $period = new DatePeriod($dateFrom, new DateInterval('P1D'), $dateTo);

        $holidays = [];

        foreach ($period as $dt) {
            $curr = $dt->format('D');

            if ($curr == 'Sun') {
                $numberOfDays--;
            } elseif (in_array($dt->format('Y-m-d'), $holidays)) {
                $numberOfDays--;
            }
        }

        $numberOfDays = $numberOfDays + 1;

        return $numberOfDays;
    }
}
