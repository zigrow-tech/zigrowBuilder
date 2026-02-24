<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>

<head>
    <title>Invoice</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style type="text/css">
        /* Dompdf-safe base resets */
        body,
        table,
        td,
        th,
        p,
        span,
        div {
            font-family: Helvetica, Arial, sans-serif !important;
            font-size: 14px !important;
            color: #111827;
        }

        * {
            font-family: Helvetica, Arial, sans-serif !important;
        }

        .wrap {
            max-width: 736px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .container-pad {
            padding: 28px 28px 24px 28px;
        }

        .border-b {
            border-bottom: 1px solid #e5e7eb;
        }

        .muted {
            color: #4b5563;
        }

        .muted-2 {
            color: #374151;
        }

        .heading {
            font-weight: 700;
            color: #111827;
        }

        .label {
            font-weight: 700;
            color: #111827;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .th,
        .td {
            border: 1px solid #d1d5db;
            padding: 8px 14px;
        }

        .w-50 {
            width: 50%;
        }

        .w-60 {
            width: 60%;
        }

        .align-right {
            text-align: right;
        }

        .align-left {
            text-align: left;
        }

        .align-middle {
            vertical-align: middle;
        }
    </style>
    <style type="text/css">
        @page {
            size: 240mm 360mm;
            /* or 230mm 320mm */
            margin: 15mm;
        }

        body,
        table,
        td,
        th,
        p,
        span,
        div {
            /* font-family: Helvetica, Arial, sans-serif; */
            /* 👈 not Helvetica/Arial */
            font-size: 14px;
            color: #111827;
        }

        .rs {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
    </style>
</head>

<body style="margin:0; padding:0;">

    <div style="display:block; padding:0px 16px;">
        <div class="wrap">

            <div class="container-pad">

                <!-- HEADER: logo left | meta right -->
                <table width="100%" class="border-b" cellspacing="0" cellpadding="0"
                    style="margin-bottom:14px; padding-bottom: 16px;">
                    <tr>
                        <!-- Left: Logo -->
                        <td class="w-50 align-middle" style="padding:0;">
                            <img src="http://127.0.0.1:5173/images/logo/logo-1_light.png" alt="Logo"
                                style="max-width:168px; height:auto; border:0; display:block;">
                        </td>

                        <!-- Right: Invoice meta (Invoice No + Date) -->
                        <td class="w-50 align-middle" align="right" style="padding:0; margin: 50px 0 0 0">
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding:0 12px 0 0;">
                                        <span class="label">Invoice No:&nbsp;</span>
                                        <span class="muted">{{ $pdfData['invoice_id'] ?? '' }}</span>
                                    </td>

                                </tr>
                                <tr>
                                    <td style="padding:0;">
                                        <span class="label">Date:&nbsp;</span>
                                        <span
                                            class="muted">{{ $pdfData['payment_date'] ? $pdfData['payment_date'] ?? '' : '' }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- PAID ribbon (kept as-is, rotated; positioned relative to .wrap) -->
                <div
                    style="position:absolute; top: -5px; right:-24px; transform:rotate(40deg);
                 background:#16a34a; color:#ffffff; padding:4px 38px;
                 font-size:14px; line-height:18px; font-weight:700; letter-spacing:.2px;">
                    PAID
                </div>

                <!-- COMPANY & CLIENT -->
                <table width="100%" class="border-b" cellspacing="0" cellpadding="0" style="margin: 12px 0 16px 0;">
                    <tr>
                        <!-- Company (Left) -->
                        <td class="w-50" valign="top" style="padding-right:16px;">
                            <div class="heading" style="margin:0 0 6px 0;">TECHNETTY</div>
                            <div class="muted-2" style="line-height:18px;">
                                <p style="margin:0 0 3px 0;">E-596, 4th Floor, Daani Plaza,</p>
                                <p style="margin:0 0 3px 0;">Dwarka Sector-7, New Delhi - 110077</p>
                                <p style="margin:0;">GST No.: 07BPNPS6133M1Z7</p>
                            </div>
                        </td>

                        <!-- Client (Right) -->
                        <td class="w-60" valign="top" style="padding-left:20px;" align="left">
                            <div class="heading" style="margin:0 0 10px 0;">Invoiced To</div>
                            <div class="muted-2" style="line-height:18px;">
                                <p style="margin:0 0 3px 0; font-weight:700;">{{ $pdfData['full_name'] }}</p>
                                   <p style="margin:0 0 3px 0; font-weight:700;">{{$pdfData['business_name']}}</p>
                                <p style="margin:0 0 3px 0;">{{$pdfData['state']}}</p>
                                <p style="margin:0 0 3px 0;">{{$pdfData['country']}}</p>
                                <p style="margin:0 0 3px 0;">{{$pdfData['postal_code']}}</p>
                                <p style="margin:0; font-weight: 700;">{{ $pdfData['gst_number']? 'GST NO. '.$pdfData['gst_number']:''}}</p>
                                 

                            </div>
                        </td>
                    </tr>
                </table>

                <!-- ORDER SUMMARY -->
                <div class="muted-2 heading"
                    style="margin:16px 0 10px 0; font-weight:600; font-family: Helvetica, Arial, sans-serif !important;">
                    Order Summary</div>
                <div style="overflow-x:auto;">
                    <table class="table" cellpadding="0" cellspacing="0" style="color:#4b5563;">
                        <thead>
                            <tr style="background:#f9fafb; color:#374151; text-transform:uppercase;">
                                <th align="left" style="padding:10px 16px; border-bottom:1px solid #e5e7eb;">Plan Name
                                </th>
                                <th align="left" style="padding:10px 16px; border-bottom:1px solid #e5e7eb;">Quantity
                                </th>
                                <th align="left" style="padding:10px 16px; border-bottom:1px solid #e5e7eb;">Price
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background:#ffffff; border-bottom:1px solid #e5e7eb;">
                                <td
                                    style="padding:10px 16px; color:#111827; font-weight:600; font-family: Helvetica, Arial, sans-serif;">
                                    {{ $pdfData['plan_name'] ?? '' }}</td>
                                <td style="padding:10px 16px;">1</td>
                                <td style="padding:10px 16px;" class="rs">₹{{ $pdfData['base_price'] }}</td>
                            </tr>

                            <tr style="background:#fcfcff; border-bottom:1px solid #e5e7eb;">
                                <td colspan="2" class="align-right"
                                    style="padding:10px 16px; color:#374151; font-weight:700;">GST (18%)</td>
                                <td style="padding:10px 16px;" class="rs">₹{{ $pdfData['gst_amount'] }}</td>
                            </tr>
                            <tr style="background:#fcfcff; border-bottom:1px solid #e5e7eb;">
                                <td colspan="2" class="align-right"
                                    style="padding:10px 16px; color:#374151; font-weight:700;">Total (Incl. GST)</td>
                                <td style="padding:10px 16px;" class="rs">₹{{ $pdfData['total_incl_gst'] }}</td>
                            </tr>
                            {{-- <tr style="background:#fcfcff; border-bottom:1px solid #e5e7eb;">
                                <td colspan="2" class="align-right"
                                    style="padding:10px 16px; color:#374151; font-weight:700;">Discount ({{$pdfData['discount_percentage']}}%)</td>
                                <td style="padding:10px 16px;" class="rs">{{$pdfData['discount_amount']? '₹'.$pdfData['discount_amount'] : '-'}}</td>
                            </tr> --}}
                            <tr style="background:#f5f7ff;">
                                <td colspan="2" class="align-right"
                                    style="padding:12px 16px; font-weight:700; color:#111827;">Total Payable</td>
                                <td style="padding:12px 16px; font-weight:700; color:#111827;" class="rs">
                                    ₹{{ $pdfData['net_amount_paid'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ORDER DETAILS -->
                <div class="muted-2 heading"
                    style="margin:18px 0 12px 0; font-weight:600; font-family: Helvetica, Arial, sans-serif;">Order
                    Details</div>
                <div style="overflow-x:auto;">
                    <table class="table" cellpadding="0" cellspacing="0" style="color:#4b5563;">
                        <tbody>
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading"
                                    style="font-weight:700; color:#111827; width:38%;">
                                    Name</th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['full_name'] }}</td>
                            </tr>
                            <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Phone
                                    Number
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['phone'] }}</td>
                            </tr>
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Email
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['email'] }}</td>
                            </tr>
                            <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">
                                    Business
                                    Name</th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['business_name'] }}</td>
                            </tr>
                            {{-- <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Subscription
                                    Start Date</th>
                                <td class="td" style="color:#1f2937;">{{$pdfData['payment_date'] ?? ''}}</td>
                            </tr>
                            <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Subscription
                                    Expiry Date</th>
                                <td class="td" style="color:#1f2937;">{{$pdfData['expiry_date'] ?? ''}}</td>
                            </tr> --}}
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Payment
                                    Date
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['payment_date'] ?? '' }}</td>
                            </tr>
                            <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Payment
                                    Time
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['payment_time'] }}</td>
                            </tr>
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Amount
                                </th>
                                <td class="td rs" style="color:#1f2937;">₹{{ $pdfData['amount'] }}</td>
                            </tr>
                            {{-- <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Discount
                                </th>
                                <td class="td rs" style="color:#1f2937;">₹{{$pdfData['discount_amount']}}</td>
                            </tr> --}}
                            {{-- <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Net
                                    Amount
                                    Paid</th>
                                <td class="td rs" style="color:#1f2937;">₹{{ $pdfData['net_amount_paid'] }}</td>
                            </tr> --}}
                            {{-- <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Offer</th>
                                <td class="td" style="color:#1f2937;">{{$pdfData['offer'] ?? '—'}}</td>
                            </tr>
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Offer
                                    Description</th>
                                <td class="td" style="color:#1f2937;">{{$pdfData['offer_desc'] ?? '—'}}</td>
                            </tr> --}}
                            <tr style="background:#f9fafb;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Payment
                                    ID
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['transaction_id'] }}</td>
                            </tr>
                            <tr style="background:#ffffff;">
                                <th align="left" class="th heading" style="font-weight:700; color:#111827;">Payment
                                    Mode
                                </th>
                                <td class="td" style="color:#1f2937;">{{ $pdfData['payment_mode'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div> <!-- /container-pad -->
        </div> <!-- /wrap -->
    </div> <!-- /outer pad -->

</body>

</html>
