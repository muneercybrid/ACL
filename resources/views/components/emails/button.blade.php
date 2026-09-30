{{--
    A call-to-action button for ACL email.

    Built as a table with a padded cell rather than a styled <a> or <button>:
    Gmail, Outlook and Yahoo strip background colours from links and ignore
    border-radius and padding on most elements, so a "button" written any other
    way arrives as an underlined word. VML keeps Outlook on Windows rendering
    the filled block at all.
--}}
@props([
    'url',
    'label',
    'color' => '#2f7d4f',
])
<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;margin:24px 0;">
    <tr>
        <td align="center" bgcolor="{{ $color }}" style="background-color:{{ $color}};border-radius:8px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                         xmlns:w="urn:schemas-microsoft-com:office:word"
                         href="{{ $url }}"
                         style="height:46px;v-text-anchor:middle;width:260px;"
                         arcsize="17%" stroke="f">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Helvetica,Arial,sans-serif;font-size:16px;font-weight:bold;">
                    {{ $label }}
                </center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $url }}"
               style="display:inline-block;padding:13px 30px;font-family:Helvetica,Arial,sans-serif;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;background-color:{{ $color}};border-radius:8px;line-height:20px;mso-hide:all;">
                {{ $label }}
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
