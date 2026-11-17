#!/usr/bin/env python3
"""
Generador de la Guía Oficial de Acceso para Socios de Santamaría Gym (SantaDoor).
Genera un PDF A4 listo para imprimir o distribuir digitalmente.
"""

import os
import base64
import shutil
import qrcode
from weasyprint import HTML

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT_DIR)

def b64_img(rel_path):
    full_path = os.path.join(ROOT_DIR, rel_path)
    with open(full_path, 'rb') as f:
        ext = rel_path.split('.')[-1]
        mime = 'image/png' if ext == 'png' else 'image/jpeg'
        return f'data:{mime};base64,{base64.b64encode(f.read()).decode()}'

# Generar QRs para descarga si no existen
os.makedirs('app/public/images', exist_ok=True)

qr_android = qrcode.QRCode(version=1, box_size=8, border=2)
qr_android.add_data('https://santamariagym.duckdns.org/SantaDoor.apk')
qr_android.make(fit=True)
img_android = qr_android.make_image(fill_color='#1E293B', back_color='#FFFFFF')
img_android.save('app/public/images/qr_download_android.png')

qr_apple = qrcode.QRCode(version=1, box_size=8, border=2)
qr_apple.add_data('https://santamariagym.duckdns.org/app')
qr_apple.make(fit=True)
img_apple = qr_apple.make_image(fill_color='#1E293B', back_color='#FFFFFF')
img_apple.save('app/public/images/qr_access_apple.png')

logo_b64 = b64_img('app/public/images/logo-round.png')
qr_android_b64 = b64_img('app/public/images/qr_download_android.png')
qr_apple_b64 = b64_img('app/public/images/qr_access_apple.png')

svg_touch = '''<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4" fill="#DC2626"/></svg>'''
svg_nfc = '''<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0"/><path d="M8.5 11.5a3.5 3.5 0 0 1 7 0"/><circle cx="12" cy="15" r="1" fill="#DC2626"/></svg>'''

html_content = f'''<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page {{
        size: A4 portrait;
        margin: 8mm 11mm 8mm 11mm;
    }}
    * {{
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }}
    body {{
        font-family: 'Montserrat', 'DejaVu Sans', sans-serif;
        color: #1E293B;
        background: #FFFFFF;
        line-height: 1.35;
        font-size: 8.8pt;
    }}

    /* ─── Cabecera Principal ─── */
    .header-table {{
        width: 100%;
        border-collapse: collapse;
        border-bottom: 3.5px solid #C81D25;
        padding-bottom: 8px;
        margin-bottom: 8px;
    }}
    .header-logo {{
        width: 66px;
        height: 66px;
        border-radius: 50%;
        border: 2px solid #C81D25;
        display: block;
    }}
    .header-title-box h1 {{
        font-size: 20pt;
        font-weight: 900;
        letter-spacing: 1px;
        color: #0F172A;
        line-height: 1.05;
    }}
    .header-title-box h1 span {{
        color: #C81D25;
    }}
    .header-title-box p {{
        font-size: 9.5pt;
        font-weight: 700;
        color: #B45309;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 1px;
    }}
    .header-badge {{
        background: #0F172A;
        color: #FFFFFF;
        padding: 7px 14px;
        border-radius: 10px;
        text-align: right;
        display: inline-block;
    }}
    .header-badge .badge-title {{
        font-size: 10.5pt;
        font-weight: 800;
        color: #F87171;
        letter-spacing: 0.5px;
    }}
    .header-badge .badge-sub {{
        font-size: 7.5pt;
        color: #94A3B8;
        margin-top: 1px;
    }}

    /* Intro banner */
    .intro-banner {{
        background: #FFF1F2;
        border-left: 4px solid #C81D25;
        padding: 7px 12px;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 8.8pt;
        color: #881337;
        line-height: 1.35;
    }}
    .intro-banner strong {{
        color: #9F1239;
    }}

    /* ─── Grid de Plataformas (Android vs Apple) ─── */
    .platforms-table {{
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        margin-bottom: 10px;
    }}
    .platforms-table td {{
        width: 50%;
        vertical-align: top;
    }}

    .platform-card {{
        border-radius: 10px;
        padding: 10px 12px;
    }}

    /* Estilo Android */
    .card-android {{
        background: #F0FDF4;
        border: 2px solid #86EFAC;
    }}
    .title-android {{
        color: #166534;
    }}
    .badge-android {{
        background: #DCFCE7;
        color: #15803D;
        border: 1px solid #86EFAC;
    }}
    .step-android {{
        background: #16A34A;
        color: #FFFFFF;
    }}

    /* Estilo Apple */
    .card-apple {{
        background: #F8FAFC;
        border: 2px solid #CBD5E1;
    }}
    .title-apple {{
        color: #0F172A;
    }}
    .badge-apple {{
        background: #E2E8F0;
        color: #1E293B;
        border: 1px solid #CBD5E1;
    }}
    .step-apple {{
        background: #0F172A;
        color: #FFFFFF;
    }}

    .card-header-table {{
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 1.5px solid rgba(0,0,0,0.08);
    }}
    .platform-h2 {{
        font-size: 11.5pt;
        font-weight: 800;
        white-space: nowrap;
    }}
    .platform-tag {{
        font-size: 7pt;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }}

    /* Pasos */
    .step-table {{
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }}
    .step-table td {{
        padding: 3px 0;
        vertical-align: top;
    }}
    .step-circle {{
        width: 18px;
        height: 18px;
        border-radius: 50%;
        font-size: 7.5pt;
        font-weight: 800;
        text-align: center;
        line-height: 18px;
        margin-right: 6px;
    }}
    .step-content {{
        font-size: 8.2pt;
        line-height: 1.3;
    }}
    .step-content strong {{
        color: #0F172A;
    }}
    .step-content code {{
        background: #FFFFFF;
        border: 1px solid rgba(0,0,0,0.12);
        padding: 1px 4px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 7.6pt;
        font-weight: bold;
        color: #C81D25;
        display: inline-block;
        margin-top: 1px;
    }}

    /* Caja de QR */
    .qr-container-table {{
        width: 100%;
        background: #FFFFFF;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.1);
        padding: 6px 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }}
    .qr-container-table td {{
        vertical-align: middle;
    }}
    .qr-code-img {{
        width: 64px;
        height: 64px;
        display: block;
    }}
    .qr-desc h4 {{
        font-size: 8.8pt;
        font-weight: 800;
        color: #0F172A;
        margin-bottom: 1px;
    }}
    .qr-desc p {{
        font-size: 7.5pt;
        color: #64748B;
        line-height: 1.25;
        margin-bottom: 2px;
    }}
    .qr-url {{
        font-size: 7pt;
        font-family: monospace;
        font-weight: 700;
        color: #C81D25;
        word-break: break-all;
    }}

    /* ─── Sección Inferior: Login y Torno ─── */
    .actions-table {{
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        margin-bottom: 8px;
    }}
    .actions-table td {{
        width: 50%;
        vertical-align: top;
    }}
    .action-box {{
        background: #F8FAFC;
        border-radius: 10px;
        padding: 10px 12px;
        border: 1.5px solid #E2E8F0;
    }}
    .box-login {{
        border-top: 3.5px solid #D97706;
    }}
    .box-door {{
        border-top: 3.5px solid #C81D25;
    }}

    .action-box h3 {{
        font-size: 10.5pt;
        font-weight: 800;
        color: #0F172A;
        margin-bottom: 8px;
    }}

    /* Credenciales */
    .cred-card {{
        background: #FFFFFF;
        border-radius: 6px;
        border: 1px solid #CBD5E1;
        padding: 6px 10px;
        margin-bottom: 6px;
    }}
    .cred-card-row {{
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 8.4pt;
    }}
    .cred-title {{
        font-weight: 700;
        color: #334155;
    }}
    .cred-badge {{
        font-family: monospace;
        font-weight: 800;
        font-size: 8.8pt;
        color: #0F172A;
        background: #F1F5F9;
        border: 1px solid #E2E8F0;
        padding: 1px 6px;
        border-radius: 4px;
    }}

    .note-warning {{
        font-size: 7.6pt;
        color: #92400E;
        background: #FEF3C7;
        border: 1px solid #FDE68A;
        border-radius: 5px;
        padding: 5px 8px;
        line-height: 1.3;
    }}

    /* Puerta items */
    .door-row {{
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 8.2pt;
        align-items: flex-start;
    }}
    .door-icon-box {{
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 1px;
    }}
    .door-text strong {{
        color: #0F172A;
    }}

    /* ─── Footer ─── */
    .footer-table {{
        width: 100%;
        border-top: 1.5px solid #E2E8F0;
        padding-top: 6px;
        font-size: 7.8pt;
        color: #64748B;
    }}
    .footer-table td {{
        vertical-align: middle;
    }}
</style>
</head>
<body>

    <!-- Cabecera -->
    <table class="header-table">
        <tr>
            <td style="width: 76px; vertical-align: middle;">
                <img class="header-logo" src="{logo_b64}" alt="Logo">
            </td>
            <td style="vertical-align: middle;">
                <div class="header-title-box">
                    <h1>SANTAMARÍA <span>GYM</span></h1>
                    <p>SantaDoor · Control de Acceso Digital</p>
                </div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div class="header-badge">
                    <div class="badge-title">GUÍA OFICIAL DE ACCESO</div>
                    <div class="badge-sub">Descarga, Instalación e Identificación</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="intro-banner">
        Accede al gimnasio cómodamente desde tu propio teléfono móvil sin necesidad de tarjeta física. Sigue los pasos según tu dispositivo (<strong>Android</strong> o <strong>iPhone / Apple</strong>) para dejar tu acceso listo en menos de 2 minutos.
    </div>

    <!-- Plataformas -->
    <table class="platforms-table">
        <tr>
            <!-- ANDROID -->
            <td>
                <div class="platform-card card-android">
                    <table class="card-header-table">
                        <tr>
                            <td><span class="platform-h2 title-android">Móvil ANDROID</span></td>
                            <td style="text-align: right;"><span class="platform-tag badge-android">App SantaDoor</span></td>
                        </tr>
                    </table>

                    <table class="step-table">
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-android">1</div></td>
                            <td class="step-content">
                                <strong>Descarga la App SantaDoor:</strong><br>
                                Escanea el código QR inferior o entra en:<br>
                                <code>santamariagym.duckdns.org/SantaDoor.apk</code>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-android">2</div></td>
                            <td class="step-content">
                                <strong>Confirmación de descarga:</strong><br>
                                Si el móvil muestra <em>"¿Descargar archivo potencialmente dañino?"</em>, pulsa <strong>"Descargar de todos modos"</strong>.
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-android">3</div></td>
                            <td class="step-content">
                                <strong>Instala y abre:</strong><br>
                                Pulsa la descarga y toca <strong>"Instalar"</strong>. Activa <em>"Permitir de esta fuente"</em> si te lo pide.
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-android">★</div></td>
                            <td class="step-content">
                                <strong>Apertura por NFC:</strong> Si tu móvil tiene NFC, puedes abrir acercando el teléfono al lector del torno.
                            </td>
                        </tr>
                    </table>

                    <table class="qr-container-table">
                        <tr>
                            <td style="width: 70px;">
                                <img class="qr-code-img" src="{qr_android_b64}" alt="QR Android">
                            </td>
                            <td style="padding-left: 8px;" class="qr-desc">
                                <h4>Escanear con Android</h4>
                                <p>Descarga directa del instalador APK.</p>
                                <div class="qr-url">santamariagym.duckdns.org/SantaDoor.apk</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- APPLE / IPHONE -->
            <td>
                <div class="platform-card card-apple">
                    <table class="card-header-table">
                        <tr>
                            <td><span class="platform-h2 title-apple">iPhone (APPLE)</span></td>
                            <td style="text-align: right;"><span class="platform-tag badge-apple">Acceso Web / PWA</span></td>
                        </tr>
                    </table>

                    <table class="step-table">
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-apple">1</div></td>
                            <td class="step-content">
                                <strong>Abre la web en Safari:</strong><br>
                                Escanea el código QR inferior o escribe en Safari:<br>
                                <code>santamariagym.duckdns.org/app</code>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-apple">2</div></td>
                            <td class="step-content">
                                <strong>Pulsa Compartir:</strong><br>
                                En la barra inferior de Safari, pulsa el botón Compartir <strong>[↑]</strong> (cuadrado con flecha hacia arriba).
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-apple">3</div></td>
                            <td class="step-content">
                                <strong>Añadir a pantalla de inicio:</strong><br>
                                Desliza hacia abajo en el menú y selecciona <strong>"Añadir a pantalla de inicio"</strong>.
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 22px;"><div class="step-circle step-apple">4</div></td>
                            <td class="step-content">
                                <strong>Confirmar:</strong><br>
                                Pulsa <strong>"Añadir"</strong> arriba a la derecha. ¡Ya tienes el icono de SantaDoor listo en tu pantalla!
                            </td>
                        </tr>
                    </table>

                    <table class="qr-container-table">
                        <tr>
                            <td style="width: 70px;">
                                <img class="qr-code-img" src="{qr_apple_b64}" alt="QR Apple">
                            </td>
                            <td style="padding-left: 8px;" class="qr-desc">
                                <h4>Escanear con iPhone</h4>
                                <p>Abre el acceso de socios en Safari.</p>
                                <div class="qr-url">santamariagym.duckdns.org/app</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Sección Inferior: Login y Apertura -->
    <table class="actions-table">
        <tr>
            <!-- LOGIN -->
            <td>
                <div class="action-box box-login">
                    <h3>🔑 1. Cómo Iniciar Sesión (Login)</h3>
                    <p style="font-size: 8.2pt; color: #475569; margin-bottom: 6px;">
                        Válido para ambos sistemas. Solo debes introducirlo la primera vez:
                    </p>
                    <div class="cred-card">
                        <div class="cred-card-row">
                            <span class="cred-title">DNI o NIE (con letra):</span>
                            <span class="cred-badge">Ej: 16555482J</span>
                        </div>
                        <div class="cred-card-row" style="border-top: 1px dashed #E2E8F0; margin-top: 3px; padding-top: 4px;">
                            <span class="cred-title">Código de Socio / ID:</span>
                            <span class="cred-badge">Ej: 4773</span>
                        </div>
                    </div>
                    <div class="note-warning">
                        <strong>¿No sabes tu número de socio?</strong> Pídelo en recepción y te lo indicamos en el mostrador en un momento.
                    </div>
                </div>
            </td>

            <!-- APERTURA -->
            <td>
                <div class="action-box box-door">
                    <h3>🚪 2. Apertura del Torno</h3>
                    <div class="door-row">
                        <div class="door-icon-box">{svg_touch}</div>
                        <div class="door-text">
                            <strong>Botón Digital (Android e iPhone):</strong><br>
                            Frente al torno, abre SantaDoor y pulsa el botón grande <strong>"ABRIR TORNO AHORA"</strong>. El torno se desbloqueará al instante.
                        </div>
                    </div>
                    <div class="door-row" style="margin-bottom: 0;">
                        <div class="door-icon-box">{svg_nfc}</div>
                        <div class="door-text">
                            <strong>Por Proximidad NFC (Exclusivo Android):</strong><br>
                            Con el móvil desbloqueado, acerca la parte trasera del teléfono al lector del torno como si fuera tu tarjeta física.
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <table class="footer-table">
        <tr>
            <td>
                <strong style="color: #0F172A;">Gimnasio Santa María</strong> · C/ Santa María, Logroño · <em>¿Dudas? Acércate a recepción y te ayudamos.</em>
            </td>
            <td style="text-align: right; color: #C81D25; font-weight: 700; white-space: nowrap;">
                SANTAMARÍA GYM · ACCESO SEGURO
            </td>
        </tr>
    </table>

</body>
</html>
'''

pdf_out = 'Guia_Acceso_SantaDoor_Santamaria_Gym.pdf'
HTML(string=html_content).write_pdf(pdf_out)
shutil.copyfile(pdf_out, 'app/public/guia-acceso.pdf')
print(f'OK: {pdf_out} y app/public/guia-acceso.pdf')
