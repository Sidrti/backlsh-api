<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Recognition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecognitionController extends Controller
{
    /**
     * Preset text mappings
     */
    protected array $presets = [
        'GREAT_WORK' => '🎉 Great work',
        'CONSISTENT_EFFORT' => '💪 Consistent effort',
        'ABOVE_AND_BEYOND' => '🚀 Above and beyond',
    ];

    /**
     * Send a recognition shoutout to a team member.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'preset_type' => 'required|string|in:GREAT_WORK,CONSISTENT_EFFORT,ABOVE_AND_BEYOND,CUSTOM',
            'message' => 'nullable|string|max:280',
        ]);

        $sender = auth()->user();
        $recipientId = (int) $request->input('recipient_id');

        if ($sender->id === $recipientId) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot send recognition to yourself.',
            ], 422);
        }

        $recipient = User::find($recipientId);
        if (!$recipient) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Recipient not found.',
            ], 404);
        }

        // Verify recipient and sender belong to the same team/organization
        $senderAdminId = $sender->getAdminId();
        $recipientAdminId = $recipient->getAdminId();
        if ($senderAdminId !== $recipientAdminId && $recipient->id !== $senderAdminId && $sender->id !== $recipientAdminId) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You can only recognize teammates in your organization.',
            ], 403);
        }

        // For regular members (non-admins), enforce a healthy weekly limit of 5 shoutouts
        if (!in_array($sender->role, ['ADMIN', 'SUBADMIN'])) {
            $weeklyGivenCount = Recognition::where('sender_id', $sender->id)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->count();

            if ($weeklyGivenCount >= 5) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'You have reached your limit of 5 recognitions for this week. It resets on Monday!',
                ], 429);
            }
        }

        // Spam guard: Max 1 recognition from the same sender to the same recipient in the last 24 hours
        $recentRecognition = Recognition::where('sender_id', $sender->id)
            ->where('recipient_id', $recipientId)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->first();

        if ($recentRecognition) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You have already sent recognition to ' . $recipient->name . ' in the last 24 hours.',
            ], 429);
        }

        // Determine message text
        $presetType = $request->input('preset_type');
        if ($presetType !== 'CUSTOM' && isset($this->presets[$presetType])) {
            $message = $this->presets[$presetType];
        } else {
            $message = trim($request->input('message', ''));
            if (empty($message)) {
                $message = '🎉 Great work';
            }
        }

        // 1. Create Recognition record
        $recognition = Recognition::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipientId,
            'preset_type' => $presetType,
            'message' => $message,
        ]);

        // 2. Create In-App Notification for Recipient
        try {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'recognition',
                'title' => '👏 Shoutout from ' . $sender->name,
                'message' => '"' . $message . '"',
                'triggered_by' => $sender->id,
                'related_type' => 'recognition',
                'related_id' => $recognition->id,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification for recognition: ' . $e->getMessage());
        }

        // 3. Send email notification to recipient
        try {
            if (!empty($recipient->email)) {
                $emailBody = view('email.recognition_email', [
                    'recipientName' => $recipient->name,
                    'senderName' => $sender->name,
                    'message' => $message,
                ])->render();

                Helper::sendEmail(
                    $recipient->email,
                    'You got a shoutout 🎉',
                    $emailBody,
                    $recipient->name
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send recognition email: ' . $e->getMessage());
        }

        // Calculate this month's count for this recipient
        $monthCount = Recognition::where('recipient_id', $recipientId)
            ->thisMonth()
            ->count();

        // Calculate this month's given count for this sender
        $senderMonthGivenCount = Recognition::where('sender_id', $sender->id)
            ->thisMonth()
            ->count();

        return response()->json([
            'status_code' => 1,
            'message' => 'Recognition sent successfully!',
            'data' => [
                'recognition' => $recognition->load('sender:id,name,email,profile_picture'),
                'recipient_month_count' => $monthCount,
                'sender_month_given_count' => $senderMonthGivenCount,
            ],
        ]);
    }

    /**
     * Fetch list of recognitions received by the authenticated user.
     */
    public function received(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 10);

        $recognitions = Recognition::where('recipient_id', $user->id)
            ->with('sender:id,name,email,profile_picture')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $monthCount = Recognition::where('recipient_id', $user->id)
            ->thisMonth()
            ->count();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'items' => $recognitions->items(),
                'month_count' => $monthCount,
                'total_count' => $recognitions->total(),
            ],
            'message' => 'Received recognitions fetched successfully',
        ]);
    }

    /**
     * Fetch recognition stats for a specific user (for showing count on report/card).
     */
    public function userStats(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = (int) $request->input('user_id');
        $monthCount = Recognition::where('recipient_id', $userId)
            ->thisMonth()
            ->count();

        $sender = auth()->user();
        $canSendToday = !Recognition::where('sender_id', $sender->id)
            ->where('recipient_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->exists();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'month_count' => $monthCount,
                'can_send_today' => $canSendToday,
            ],
            'message' => 'Recognition stats fetched successfully',
        ]);
    }

    /**
     * Fetch public recognition activity feed for the company / team.
     * Shows: "Arti gave Siddhant a shoutout 👏"
     */
    public function feed(Request $request): JsonResponse
    {
        $user = auth()->user();
        $adminId = $user->getAdminId();
        $limit = (int) $request->input('limit', 15);

        $feed = Helper::getRecognitionActivityFeed($adminId, $limit);

        return response()->json([
            'status_code' => 1,
            'data' => $feed,
            'message' => 'Recognition activity feed fetched successfully',
        ]);
    }

    /**
     * Fetch pending recognition recommendation prompts for the manager.
     */
    public function prompts(Request $request): JsonResponse
    {
        $user = auth()->user();
        $adminId = $user->getAdminId();

        $prompts = \App\Models\RecognitionPrompt::where('admin_id', $adminId)
            ->where('status', 'PENDING')
            ->with('candidate:id,name,email,profile_picture')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status_code' => 1,
            'data' => $prompts,
            'message' => 'Recognition prompts fetched successfully',
        ]);
    }

    /**
     * Dismiss a recognition prompt so it doesn't clutter the manager's board.
     */
    public function dismissPrompt(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $adminId = $user->getAdminId();

        $prompt = \App\Models\RecognitionPrompt::where('id', $id)
            ->where('admin_id', $adminId)
            ->first();

        if ($prompt) {
            $prompt->update(['status' => 'DISMISSED']);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Prompt dismissed successfully',
        ]);
    }
}
