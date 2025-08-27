<tr>
    <td>
        <table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="content-cell" align="center">
                    <div style="margin-bottom: 24px;">
                        <div style="color: #e67e22; font-size: 18px; font-weight: 600; margin-bottom: 12px;">Corvallis
                            Music Collective</div>
                        <div style="color: #4a5568; font-size: 14px; line-height: 1.6;">
                            <p>Building & connecting music communities in Corvallis</p>
                            <p style="margin: 8px 0;">
                                <a href="mailto:contact@corvmc.org"
                                    style="color: #e67e22; text-decoration: none;">contact@corvmc.org</a> |
                                <a href="https://corvmc.org"
                                    style="color: #e67e22; text-decoration: none;">corvmc.org</a>
                            </p>
                        </div>
                    </div>
                    {{ Illuminate\Mail\Markdown::parse($slot) }}
                </td>
            </tr>
        </table>
    </td>
</tr>
