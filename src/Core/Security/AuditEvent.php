<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
enum AuditEvent: string
{
    case FinancialPosted = 'finance.posted';
    case CatalogChanged = 'catalog.changed';
    case OrderChanged = 'orders.changed';
    case AccessDenied = 'access.denied';
    case JobReconciled = 'job.reconciled';
    case SessionEnded = 'session.ended';
    case SessionRevoked = 'session.revoked';
    case SessionRotated = 'session.rotated';
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case MfaChallenged = 'mfa.challenged';
    case MfaSucceeded = 'mfa.succeeded';
    case MfaFailed = 'mfa.failed';
    case MfaEnrolled = 'mfa.enrolled';
    case MfaRecoveryUsed = 'mfa.recovery_used';
    case PermissionChanged = 'permission.changed';
    case RoleAssigned = 'role.assigned';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';
    case ApiKeyRotated = 'api_key.rotated';
    case ApiKeyDenied = 'api_key.denied';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';
    case ImpersonationDenied = 'impersonation.denied';
    case ConfigurationChanged = 'configuration.changed';
    case UserCreated = 'user.created';
    case EmailVerified = 'email.verified';
    case PasswordChanged = 'password.changed';
    case InvitationCreated = 'invitation.created';
    case InvitationAccepted = 'invitation.accepted';
    case SuspiciousLogin = 'login.suspicious';
    case MfaRemoved = 'mfa.removed';
    case MfaRecoveryReplaced = 'mfa.recovery_replaced';
    case DeviceCreated = 'device.created';
    case DeviceRevoked = 'device.revoked';
    case SessionReauthenticated = 'session.reauthenticated';
    case CriticalPrepared = 'critical.prepared';
    case CriticalConfirmed = 'critical.confirmed';
}
