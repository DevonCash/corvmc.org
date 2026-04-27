<?php

namespace CorvMC\Moderation\Enums;

enum ApprovalWorkflow: string
{
    case AutoApprove = 'auto_approve';
    case TrustedReview = 'trusted_review';
    case StandardReview = 'standard_review';
    case RequireAdminReview = 'require_admin_review';
}
