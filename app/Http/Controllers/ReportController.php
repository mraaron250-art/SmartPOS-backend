<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;
use App\Support\Validate;

final class ReportController extends Controller
{
    public function dashboard(Request $request): Response
    {
        return $this->ok((new ReportService())->dashboard());
    }

    public function sales(Request $request): Response
    {
        return $this->ok((new ReportService())->sales(
            Validate::date($request->query('date_from')),
            Validate::date($request->query('date_to')),
        ));
    }

    public function productSales(Request $request): Response
    {
        return $this->ok((new ReportService())->productSales(
            Validate::date($request->query('date_from')),
            Validate::date($request->query('date_to')),
        ));
    }
}
