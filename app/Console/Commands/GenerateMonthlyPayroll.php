<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\PayrollService;

class GenerateMonthlyPayroll extends Command
{
    protected $signature = 'payroll:generate {--month=} {--year=}';
    protected $description = 'Generate payroll for a given month (defaults to the previous month)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(PayrollService $payrollService)
    {
        $month = $this->option('month');
        $year = $this->option('year');

        if (!$month || !$year) {
            $previousMonth = Carbon::now()->subMonth();
            $month = $previousMonth->month;
            $year = $previousMonth->year;
        }

        $results = $payrollService->generate((int) $year, (int) $month);

        $this->info("Payroll generated for {$year}-{$month}: {$results['generated']} generated, {$results['skipped']} skipped.");

        return 0;
    }
}
