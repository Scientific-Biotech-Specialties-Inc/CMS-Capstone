<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Expiry Notice</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Fallbacks and resets */
        body, table, td, a { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #F4F5F8;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        /* Mobile styling */
        @media screen and (max-width: 600px) {
            .container-table {
                width: 100% !important;
            }
            .content-cell {
                padding: 24px 20px !important;
            }
            .header-cell {
                padding: 20px 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #F4F5F8;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #F4F5F8;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="600">
                <tr>
                <td align="center" valign="top" width="600">
                <![endif]-->
                <table class="container-table" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(37, 37, 120, 0.04); border: 1px solid #EAEBF4;">
                    <!-- Top accent line -->
                    <tr>
                        <td height="4" style="background-color: #2E85D8; line-height: 4px; font-size: 4px;">&nbsp;</td>
                    </tr>
                    
                    @php
                        $badgeText = 'Contract Alert';
                        if ($notificationType === 'expiry_30') $badgeText = 'Renewal Reminder';
                        elseif ($notificationType === 'expiry_90') $badgeText = 'Advance Notice';
                    @endphp
                    
                    <!-- Header -->
                    <tr>
                        <td class="header-cell" style="padding: 24px 32px; background-color: #2F2F73;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <!-- Logo on Left -->
                                    <td align="left" valign="middle">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding-right: 12px;">
                                                    <table border="0" cellpadding="0" cellspacing="0" style="background-color: #2E85D8; border-radius: 6px;">
                                                        <tr>
                                                            <td style="padding: 8px;">
                                                                <!-- Simple White Grid Icon -->
                                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="display: block;">
                                                                    <rect x="3" y="3" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                                                                    <rect x="11" y="3" width="6" height="6" rx="1.5" fill="white" opacity="0.5"/>
                                                                    <rect x="3" y="11" width="6" height="6" rx="1.5" fill="white" opacity="0.5"/>
                                                                    <rect x="11" y="11" width="6" height="6" rx="1.5" fill="white" opacity="0.9"/>
                                                                </svg>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="font-family: 'Poppins', sans-serif; font-size: 18px; font-weight: 600; color: #FFFFFF;">
                                                    CMS <span style="font-weight: 400; color: rgba(255, 255, 255, 0.65);">SBSI</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <!-- Status Badge on Right -->
                                    <td align="right" valign="middle">
                                        <span style="font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: #FFFFFF; background-color: rgba(255, 255, 255, 0.12); padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.15); display: inline-block;">
                                            {{ $badgeText }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    @php
                        // Alert configurations using solid hex color codes for high-compatibility
                        $alertBg = '#FAFBFD'; 
                        $borderLeftColor = '#C1C4D6'; 
                        $alertLabel = 'General Update';
                        $iconStroke = '#252578';
                        $iconPath = 'M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z';
                        $actionPill = 'Action Required';
                        $textColor = '#2F2F73';
                        $titleColor = '#8F91A2';
                        $iconWrapperBg = '#F0F1F6';

                        if ($notificationType === 'expiry_1') {
                            $alertBg = '#2F2F73'; // Dark Navy background for urgent alert
                            $borderLeftColor = '#2E85D8'; 
                            $alertLabel = 'Urgent Notice';
                            $iconStroke = '#2E85D8';
                            $iconPath = 'M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z';
                            $actionPill = 'Renew Now';
                            $textColor = '#FFFFFF';
                            $titleColor = '#2E85D8';
                            $iconWrapperBg = '#3F3F80';
                        } elseif ($notificationType === 'expiry_30') {
                            $alertBg = '#FAF9FD'; 
                            $borderLeftColor = '#252578'; 
                            $alertLabel = 'Warning';
                            $iconStroke = '#252578';
                            $iconPath = 'M12 8v4l3 3M12 2a10 10 0 110 20A10 10 0 0112 2z';
                            $actionPill = 'Renewal Required';
                            $textColor = '#2F2F73';
                            $titleColor = '#252578';
                            $iconWrapperBg = '#F0F0F8';
                        } elseif ($notificationType === 'expiry_90') {
                            $alertBg = '#F6F9FD'; 
                            $borderLeftColor = '#2E85D8'; 
                            $alertLabel = 'Notice';
                            $iconStroke = '#2E85D8';
                            $iconPath = 'M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z';
                            $actionPill = 'Review & Renew';
                            $textColor = '#2F2F73';
                            $titleColor = '#2E85D8';
                            $iconWrapperBg = '#ECF3FC';
                        }
                    @endphp
                    
                    <!-- Content Body -->
                    <tr>
                        <td class="content-cell" style="padding: 40px 32px;">
                            <h1 style="font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 700; color: #252578; margin: 0 0 8px 0; letter-spacing: -0.3px;">
                                Hello, {{ $recipientName }}!
                            </h1>
                            <p style="font-family: 'Poppins', sans-serif; font-size: 14px; color: #535380; line-height: 1.6; margin: 0 0 32px 0;">
                                You have an important contract notification that requires your attention.
                            </p>
                            
                            <!-- Alert Card -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: {{ $alertBg }}; border-left: 4px solid {{ $borderLeftColor }}; border-radius: 8px; margin-bottom: 32px;">
                                <tr>
                                    <!-- Icon Column -->
                                    <td valign="top" style="padding: 20px 0 20px 20px; width: 36px;">
                                        <table border="0" cellpadding="0" cellspacing="0" style="background-color: {{ $iconWrapperBg }}; border-radius: 50%; width: 36px; height: 36px;">
                                            <tr>
                                                <td align="center" valign="middle" style="height: 36px; width: 36px;">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: block;">
                                                        <path d="{{ $iconPath }}"
                                                              stroke="{{ $iconStroke }}"
                                                              stroke-width="2"
                                                              stroke-linecap="round"
                                                              stroke-linejoin="round"/>
                                                    </svg>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <!-- Message Column -->
                                    <td valign="top" style="padding: 20px; font-family: 'Poppins', sans-serif;">
                                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; color: {{ $titleColor }};">
                                            {{ $alertLabel }}
                                        </div>
                                        <div style="font-size: 13px; line-height: 1.6; font-weight: 500; color: {{ $textColor }};">
                                            {{ $messageText }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Metadata details table -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 36px;">
                                @if($contractId)
                                <tr style="border-bottom: 1px solid #EAEBF4;">
                                    <th align="left" style="padding: 14px 0; font-family: 'Poppins', sans-serif; font-size: 13px; color: #8F91A2; font-weight: 500; width: 40%;">Contract ID</th>
                                    <td align="right" style="padding: 14px 0; font-family: 'Poppins', sans-serif; font-size: 13px; color: #252578; font-weight: 600;">#{{ $contractId }}</td>
                                </tr>
                                @endif
                                <tr style="border-bottom: 1px solid #EAEBF4;">
                                    <th align="left" style="padding: 14px 0; font-family: 'Poppins', sans-serif; font-size: 13px; color: #8F91A2; font-weight: 500;">Required Action</th>
                                    <td align="right" style="padding: 14px 0;">
                                        <span style="font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; color: #2E85D8; background-color: #EBF3FC; padding: 4px 12px; border-radius: 20px; display: inline-block; letter-spacing: 0.2px;">
                                            {{ $actionPill }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th align="left" style="padding: 14px 0; font-family: 'Poppins', sans-serif; font-size: 13px; color: #8F91A2; font-weight: 500;">System Status</th>
                                    <td align="right" style="padding: 14px 0; font-family: 'Poppins', sans-serif; font-size: 13px; color: #252578; font-weight: 600;">Renewal Pipeline</td>
                                </tr>
                            </table>
                            
                            @php
                                $rolePrefix = 'sales'; // Default fallback
                                if (isset($userRole)) {
                                    if ($userRole === 'Admin') {
                                        $rolePrefix = 'admin';
                                    } elseif (in_array($userRole, ['Manager'])) {
                                        $rolePrefix = 'manager';
                                    } elseif (in_array($userRole, ['Sales', 'Employee', 'Finance'])) {
                                        $rolePrefix = 'sales';
                                    }
                                }
                                $portalUrl = config('app.frontend_url', 'http://localhost:5173') . '/' . $rolePrefix . '/contracts';
                            @endphp
                            
                            <!-- Call to Action Section -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-top: 10px;">
                                        <a href="{{ $portalUrl }}" 
                                           style="display: inline-block; background-color: #2E85D8; color: #FFFFFF; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; letter-spacing: 0.2px; box-shadow: 0 4px 12px rgba(46, 133, 216, 0.2);">
                                            Access Contract Portal &rarr;
                                        </a>
                                        <p style="margin: 16px 0 0 0; font-family: 'Poppins', sans-serif; font-size: 11px; color: #8F91A2; word-break: break-all;">
                                            {{ $portalUrl }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer Section -->
                    <tr>
                        <td style="padding: 28px 32px; background-color: #FAFBFD; border-top: 1px solid #EAEBF4; text-align: center; font-family: 'Poppins', sans-serif;">
                            <p style="margin: 0 0 10px 0; font-family: 'Poppins', sans-serif; font-size: 11px; color: #8F91A2; line-height: 1.8;">
                                This is an automated notification from the CMS Contract Management System.<br>
                                Please do not reply directly to this email.
                            </p>
                            <p style="margin: 0; font-family: 'Poppins', sans-serif; font-size: 10px; color: #B0B2C3; font-weight: 500;">
                                &copy; {{ date('Y') }} CMS Capstone. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
