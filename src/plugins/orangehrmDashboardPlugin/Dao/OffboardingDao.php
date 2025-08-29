<?php

namespace OrangeHRM\Dashboard\Dao;

use OrangeHRM\Core\Dao\BaseDao;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\EmployeeTerminationRecord;

class OffboardingDao extends BaseDao
{
    public function getTerminatedEmployeesCount(string $startDate, string $endDate): int
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select($qb->expr()->count('etr.id'))
            ->where($qb->expr()->between('etr.date', ':startDate', ':endDate'))
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalActiveEmployees(): int
    {
        $qb = $this->createQueryBuilder(Employee::class, 'e');
        $qb->select($qb->expr()->count('e.empNumber'))
            ->leftJoin('e.employeeTerminationRecord', 'etr')
            ->where($qb->expr()->isNull('etr.id'));

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getTerminationReasonsBreakdown(string $startDate, string $endDate): array
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('tr.name as reason', $qb->expr()->count('etr.id') . ' as count')
            ->leftJoin('etr.terminationReason', 'tr')
            ->where($qb->expr()->between('etr.date', ':startDate', ':endDate'))
            ->groupBy('tr.id')
            ->orderBy('count', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $results = $qb->getQuery()->getArrayResult();
        
        return array_map(function($row) {
            return [
                'reason' => $row['reason'] ?? 'Not Specified',
                'count' => (int)$row['count']
            ];
        }, $results);
    }

    public function getRecentDepartures(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('e.empNumber', 'e.firstName', 'e.lastName', 'jt.jobTitleName as jobTitle', 'etr.date as terminationDate', 'tr.name as reason')
            ->join('etr.employee', 'e')
            ->leftJoin('e.jobTitle', 'jt')
            ->leftJoin('etr.terminationReason', 'tr')
            ->orderBy('etr.date', 'DESC')
            ->setMaxResults($limit);

        $results = $qb->getQuery()->getArrayResult();
        
        return array_map(function($row) {
            return [
                'empNumber' => $row['empNumber'],
                'firstName' => $row['firstName'],
                'lastName' => $row['lastName'],
                'jobTitle' => $row['jobTitle'] ?? 'N/A',
                'terminationDate' => $row['terminationDate']?->format('Y-m-d'),
                'reason' => $row['reason'] ?? 'Not Specified'
            ];
        }, $results);
    }

    public function getMonthlyTerminationTrend(): array
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('YEAR(etr.date) as year', 'MONTH(etr.date) as month', $qb->expr()->count('etr.id') . ' as count')
            ->where('etr.date >= :startDate')
            ->groupBy('year', 'month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->setParameter('startDate', date('Y-m-d', strtotime('-12 months')))
            ->setMaxResults(12);

        $results = $qb->getQuery()->getArrayResult();
        
        return array_map(function($row) {
            return [
                'period' => sprintf('%04d-%02d', $row['year'], $row['month']),
                'count' => (int)$row['count']
            ];
        }, $results);
    }

    public function getDepartmentTerminationBreakdown(string $startDate, string $endDate): array
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('su.name as department', $qb->expr()->count('etr.id') . ' as count')
            ->join('etr.employee', 'e')
            ->leftJoin('e.subDivision', 'su')
            ->where($qb->expr()->between('etr.date', ':startDate', ':endDate'))
            ->groupBy('su.id')
            ->orderBy('count', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $results = $qb->getQuery()->getArrayResult();
        
        return array_map(function($row) {
            return [
                'department' => $row['department'] ?? 'Unknown Department',
                'count' => (int)$row['count']
            ];
        }, $results);
    }

    public function getTerminationsByJobTitle(string $startDate, string $endDate): array
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('jt.jobTitleName as jobTitle', $qb->expr()->count('etr.id') . ' as count')
            ->join('etr.employee', 'e')
            ->leftJoin('e.jobTitle', 'jt')
            ->where($qb->expr()->between('etr.date', ':startDate', ':endDate'))
            ->groupBy('jt.id')
            ->orderBy('count', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setMaxResults(10);

        $results = $qb->getQuery()->getArrayResult();
        
        return array_map(function($row) {
            return [
                'jobTitle' => $row['jobTitle'] ?? 'Unknown Position',
                'count' => (int)$row['count']
            ];
        }, $results);
    }

    public function getAverageTenure(): float
    {
        $qb = $this->createQueryBuilder(EmployeeTerminationRecord::class, 'etr');
        $qb->select('AVG(DATEDIFF(etr.date, e.joinedDate)) as avgTenure')
            ->join('etr.employee', 'e')
            ->where('e.joinedDate IS NOT NULL')
            ->andWhere('etr.date >= :startDate')
            ->setParameter('startDate', date('Y-01-01'));

        $result = $qb->getQuery()->getSingleScalarResult();
        return round($result / 30.44, 1); // Convert days to months
    }
}
