<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High-Risk Contract Approval Overdue</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body, table, td, a { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { margin: 0; padding: 0; width: 100% !important; background-color: #F4F5F8; }
        @media screen and (max-width: 600px) {
            .container-table { width: 100% !important; }
            .content-cell { padding: 24px 20px !important; }
            .header-cell { padding: 20px 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #F4F5F8;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #F4F5F8;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table class="container-table" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(37, 37, 120, 0.04); border: 1px solid #EAEBF4;">
                    <tr>
                        <td height="4" style="background-color: #DC2626; line-height: 4px; font-size: 4px;">&nbsp;</td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td class="header-cell" style="padding: 24px 32px; background-color: #2F2F73;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="left" valign="middle" style="font-family: 'Poppins', sans-serif; font-size: 18px; font-weight: 600; color: #FFFFFF;">
                                        CMS <span style="font-weight: 400; color: rgba(255, 255, 255, 0.65);">SBSI</span>
                                    </td>
                                    <td align="right" valign="middle">
                                        <span style="font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: #FFFFFF; background-color: rgba(220, 38, 38, 0.35); padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.15); display: inline-block;">
                                            SLA Breach
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content-cell" style="padding: 40px 32px;">
                            <h1 style="font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 700; color: #252578; margin: 0 0 8px 0;">
                                Hello, {{ $recipientName }}!
                            </h1>
                            <p style="font-family: 'Poppins', sans-serif; font-size: 14px; color: #535380; line-height: 1.6; margin: 0 0 32px 0;">
                                A High-Risk contract you were assigned to approve has been pending for
                                over 24 hours and now requires your immediate attention.
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: #FEF2F2; border-left: 4px solid #DC2626; border-radius: 8px; margin-bottom: 32px;">
                                <tr>
                                    <td valign="top" style="padding: 20px; font-family: 'Poppins', sans-serif;">
                                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; color: #DC2626;">
                                            {{ ucfirst($riskLevel) }} Risk — Approval Overdue
                                        </div>
                                        <div style="font-size: 13px; line-height: 1.6; font-weight: 500; color: #7F1D1D;">
                                            Contract "{{ $bpName }}" (#{{ $contractId }}) has been awaiting your
                                            approve/reject decision for {{ $hoursOverdue }} hour(s), exceeding the
                                            24-hour SLA window for high-risk contracts.
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-top: 10px;">
                                        <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/manager/contracts/{{ $contractId }}"
                                           style="display: inline-block; background-color: #DC2626; color: #FFFFFF; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600;">
                                            Review Contract Now &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

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
            </td>
        </tr>
    </table>
</body>
</html>
