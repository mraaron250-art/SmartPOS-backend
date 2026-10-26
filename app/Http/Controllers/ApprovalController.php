<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApprovalRequest;
use App\Services\AuditService;
use App\Support\Validate;
use Illuminate\Database\Capsule\Manager as DB;

final class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ApprovalRequest::query()->orderByDesc('created_at')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($module = $request->query('module')) {
            $query->where('module', $module);
        }

        return $this->ok($query->get()->toArray());
    }

    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $action = Validate::text($request->input('action', ''), 100);
        $module = Validate::text($request->input('module', ''), 50);

        if ($action === '' || $module === '') {
            throw HttpException::badRequest('Action and module are required.');
        }

        $targetId = $request->input('target_id');
        $approval = ApprovalRequest::query()->create([
            'requested_by' => $user->id,
            'requester_name' => $user->name,
            'action' => $action,
            'module' => $module,
            'target_type' => Validate::text($request->input('target_type', ''), 50),
            'target_id' => $targetId !== null && $targetId !== '' ? Validate::text((string) $targetId, 100) : null,
            'reason' => Validate::text($request->input('reason', ''), 1000),
            'status' => 'PENDING',
        ])->refresh();

        AuditService::log($user, [
            'action' => 'APPROVAL_REQUEST_SUBMIT',
            'module' => 'APPROVAL',
            'target_type' => 'APPROVAL_REQUEST',
            'target_id' => $approval->id,
            'details' => "Submitted approval request: {$action} in {$module}",
        ]);

        return $this->created($approval->toArray(), 'Approval request submitted successfully.');
    }

    public function approve(Request $request): Response
    {
        $approval = $this->review($request, 'APPROVED', 'Approved by manager');
        return $this->ok($approval->toArray(), 'Request approved successfully.');
    }

    public function reject(Request $request): Response
    {
        $approval = $this->review($request, 'REJECTED', 'Rejected by manager');
        return $this->ok($approval->toArray(), 'Request rejected.');
    }

    private function review(Request $request, string $status, string $defaultComment): ApprovalRequest
    {
        $reviewer = $this->user($request);
        $id = (string) $request->param('id');
        $comment = Validate::text($request->input('comment') ?: $defaultComment, 1000);

        $approval = DB::connection()->transaction(function () use ($reviewer, $id, $status, $comment) {
            $approval = ctype_digit($id) ? ApprovalRequest::query()->lockForUpdate()->find((int) $id) : null;
            if ($approval === null) {
                throw HttpException::badRequest('Approval request not found.');
            }
            if ($approval->status !== 'PENDING') {
                throw HttpException::badRequest("Cannot review request already marked as {$approval->status}.");
            }
            if ((int) $approval->requested_by === (int) $reviewer->id) {
                throw HttpException::badRequest('Self-approval is forbidden: users cannot approve their own requests.');
            }

            $approval->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewer_name' => $reviewer->name,
                'review_comment' => $comment,
                'reviewed_at' => new \DateTimeImmutable(),
            ]);
            return $approval;
        });

        $verb = $status === 'APPROVED' ? 'Approved' : 'Rejected';
        AuditService::log($reviewer, [
            'action' => "APPROVAL_REQUEST_{$status}",
            'module' => 'APPROVAL',
            'target_type' => 'APPROVAL_REQUEST',
            'target_id' => $approval->id,
            'details' => "{$verb} request {$approval->id}: {$approval->action}",
        ]);

        return $approval;
    }
}
