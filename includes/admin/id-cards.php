<?php
    /**
 * ID card design settings and the generation screen.
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    /**
 * ID Cards screen.
 */
    function sp_admin_page_sp_id_cards()
    {
    $settings = sp_idcard_settings();
    $students = sp_get_profiles(['per_page' => 500]);
    $selected = isset($_GET['sp_student']) ? (int) $_GET['sp_student'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
    $profile  = $selected ? sp_get_profile_by_id($selected) : null;

    if (! $profile && $students) {
        $profile  = sp_get_profile_by_id((int) $students[0]->id);
        $selected = (int) $students[0]->id;
    }

    /*
         * A complete artwork already prints its own corners, logo and QR code, so
         * those three slots are hidden while it is active — only the background
         * survives both modes, because in artwork mode it carries the artwork
         * itself. The signature stays in both modes: a hand-written sign is one
         * thing no artwork can print, so artwork mode lays it over the design.
         */
    $is_artwork = sp_idcard_is_artwork();

    $uploads = [
        'background_id' => [
            'label' => $is_artwork ? 'Artwork image' : 'Background image',
            'hint'  => $is_artwork
                ? 'The finished card artwork. Leave empty to use the design bundled with the plugin.'
                : 'The backdrop behind the generated frame. Leave empty for a plain white card.',
        ],
        'corner_id'     => ['label' => 'Corner design', 'hint' => 'Placed in all four corners of the card.'],
        'logo_id'       => ['label' => 'Logo', 'hint' => 'Shown in the header next to the institute name.'],
        'qr_id'         => ['label' => 'QR code', 'hint' => 'The scan code printed at the bottom left.'],
        'signature_id'  => [
            'label' => 'Signature',
            'hint'  => $is_artwork
                ? 'Laid over the artwork in the empty block beside the QR code. Leave empty to use the signature bundled with the plugin.'
                : 'Director signature at the bottom right.',
        ],
    ];

    $total_steps = $is_artwork ? 2 : 3;
    ?>
<div class="sp-idcu-ui" data-sp-idc-mode="<?php echo $is_artwork ? 'artwork' : 'frame'; ?>">

    <div class="sp-idcu-page-header">
        <div class="sp-idcu-page-heading">
            <span class="sp-idcu-eyebrow">
                <span class="sp-idcu-eyebrow-mark" aria-hidden="true">◆</span>
                STUDENT PORTAL <span aria-hidden="true">/</span> DOCUMENTS
            </span>
            <h1>ID cards</h1>
            <p>Design the card once, pick a student, then download the finished card as JPG or PDF. Cards are generated
            </p>
        </div>
        <div class="sp-idcu-header-actions">
            <div class="sp-idcu-header-note">
                <span class="sp-idcu-header-mark" aria-hidden="true">▣</span>
                <span>
                    <strong>JPG + PDF</strong>
                    <small>Rendered in the browser</small>
                </span>
            </div>
            <!-- <div class="sp-idcu-header-mode">
                <span class="sp-idcu-mode-dot <?php echo $is_artwork ? 'is-artwork' : 'is-frame'; ?>"
                    aria-hidden="true"></span>
                <span><?php echo $is_artwork ? 'Artwork mode' : 'Plain backdrop'; ?></span>
            </div> -->
        </div>
    </div>

    <?php if (! empty($_GET['saved'])): ?>
    <div class="sp-idcu-notice sp-idcu-notice-success" role="status">
        <span class="sp-idcu-notice-icon" aria-hidden="true">✓</span>
        <span>Design saved. The preview on the right already reflects the new artwork.</span>
    </div>
    <?php endif; ?>

    <div class="sp-idcu-layout">

        <section class="sp-idcu-panel sp-idcu-panel-design" aria-labelledby="sp-idcu-settings-title">
            <div class="sp-idcu-panel-heading">
                <div>
                    <span class="sp-idcu-section-kicker">DESIGN</span>
                    <h2 id="sp-idcu-settings-title">Card design</h2>
                    <p>Upload the artwork once — every preview and download picks it up.</p>
                </div>
                <span class="sp-idcu-heading-icon" aria-hidden="true">✎</span>
            </div>

            <!-- NOT NEEDED -->
            <ol class="sp-idcu-progress" aria-hidden="true">
                <li class="is-active"><span>01</span> Background</li>
                <li class="is-active <?php echo $is_artwork ? 'is-current' : ''; ?>"><span>02</span> Artwork
                    layout<?php echo $is_artwork ? '' : ' (artwork)'; ?></li>
                <?php if (! $is_artwork): ?>
                <li class="is-current"><span>03</span> Card text</li>
                <?php else: ?>
                <li class="is-locked"><span>03</span> Card text <em>(hidden in artwork mode)</em></li>
                <?php endif; ?>
            </ol>

            <form method="post" enctype="multipart/form-data" class="sp-idcu-form">
                <?php sp_admin_nonce_field('save_idcard_settings'); ?>
                <!-- NOT NEEDED NOW  -->
                <div class="sp-idcu-step" data-sp-idc-step="1">
                    <div class="sp-idcu-step-head">
                        <span class="sp-idcu-step-num">01</span>
                        <div>
                            <strong>Background style</strong>
                            <small>Pick whether the image is a finished artwork or a plain backdrop.</small>
                        </div>
                    </div>

                    <div class="sp-idcu-field">
                        <label class="sp-idcu-radio">
                            <input type="radio" name="background_mode" value="artwork" data-sp-idc-mode
                                <?php checked($settings['background_mode'], 'artwork'); ?> />
                            <span>
                                <strong>Complete artwork</strong>
                                <small>The image already contains the frame, header, field labels, QR code and footer.
                                    Only the photo and the values are printed on top.</small>
                            </span>
                        </label>
                        <label class="sp-idcu-radio">
                            <input type="radio" name="background_mode" value="frame" data-sp-idc-mode
                                <?php checked($settings['background_mode'], 'frame'); ?> />
                            <span>
                                <strong>Plain backdrop</strong>
                                <small>The image is only a backdrop; the plugin draws the frame, header, labels, QR code
                                    and footer itself.</small>
                            </span>
                        </label>
                    </div>
                </div>

                <?php foreach ($uploads as $sp_key => $sp_upload): ?>
                <?php
                    $sp_asset_id   = isset($settings[$sp_key]) ? (int) $settings[$sp_key] : 0;
                        $sp_thumb      = $sp_asset_id ? wp_get_attachment_image_url($sp_asset_id, 'thumbnail') : '';
                        $sp_frame_only = ! in_array($sp_key, ['background_id', 'signature_id'], true);
                        $sp_hide       = $sp_frame_only && $is_artwork;
                    ?>
                <div class="sp-idcu-field sp-idcu-upload<?php echo $sp_thumb ? ' has-image' : ''; ?>"
                    <?php echo $sp_frame_only ? ' data-sp-idc-frame-only' : ''; ?><?php echo $sp_hide ? ' hidden' : ''; ?>>
                    <label
                        for="sp_idc_<?php echo esc_attr($sp_key); ?>"><?php echo esc_html($sp_upload['label']); ?></label>
                    <div class="sp-idcu-upload-row">
                        <span class="sp-idcu-thumb<?php echo $sp_thumb ? '' : ' is-empty'; ?>">
                            <?php if ($sp_thumb): ?>
                            <img src="<?php echo esc_url($sp_thumb); ?>" alt="" />
                            <?php else: ?>
                            <span class="sp-idcu-thumb-plus" aria-hidden="true">＋</span>
                            <?php endif; ?>
                        </span>
                        <div class="sp-idcu-upload-controls">
                            <input type="file" id="sp_idc_<?php echo esc_attr($sp_key); ?>"
                                name="<?php echo esc_attr($sp_key); ?>" accept="image/*" />
                            <span class="sp-idcu-upload-hint">PNG or JPG, transparent allowed</span>
                        </div>
                    </div>
                    <p class="description">
                        <?php echo esc_html($sp_upload['hint']); ?>
                        <?php if ($sp_asset_id): ?>
                        <label class="sp-idcu-clear"><input type="checkbox"
                                name="clear_<?php echo esc_attr($sp_key); ?>" value="1" /> Remove current
                            image</label>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <div class="sp-idcu-callout" data-sp-idc-artwork-only<?php echo $is_artwork ? '' : ' hidden'; ?>>
                    <span class="sp-idcu-callout-icon" aria-hidden="true">ⓘ</span>
                    <p>Corner design, logo and QR code are hidden in this mode because the artwork already prints them.
                        The signature is laid over the artwork instead, so its upload stays available.</p>
                </div>

                <div class="sp-idcu-divider" data-sp-idc-frame-only<?php echo $is_artwork ? ' hidden' : ''; ?>></div>

                <div class="sp-idcu-form-section-title"
                    data-sp-idc-artwork-only<?php echo $is_artwork ? '' : ' hidden'; ?>>
                    <span>02</span>
                    <div>
                        <strong>Artwork layout</strong>
                        <small>Where the photo and the field values sit on a complete artwork.</small>
                    </div>
                </div>

                <div class="sp-idcu-step-body" data-sp-idc-artwork-only
                    <?php echo 'artwork' !== $settings['background_mode'] ? 'hidden' : ''; ?>>
                    <p class="description">Every value is a percentage of the card, so the layout stays correct if the
                        card is resized. They are pre-set for the design bundled with the plugin; nudge them until the
                        photo sits inside the printed ring and each value lines up with its printed label.</p>
                    <p class="description">The signature keeps the proportions of the image: its height follows from its
                        width, so only the width is set here. The bundled sign is 685 × 338. The rule and the name under
                        the sign are drawn with it.</p>

                    <?php
                        $sp_art_fields = [
                                'art_photo_left' => ['Photo left', 'Percent of width'],
                                'art_photo_top'  => ['Photo top', 'Percent of height'],
                                'art_photo_size' => ['Photo size', 'Percent of width'],
                                'art_value_left' => ['Values left', 'Percent of width'],
                                'art_value_size' => ['Value size', 'Percent of width'],
                                'art_sign_left'  => ['Signature left', 'Percent of width'],
                                'art_sign_top'   => ['Signature top', 'Percent of height'],
                                'art_sign_width' => ['Signature width', 'Percent of width'],
                                'art_note_top'   => ['Bottom line top', 'Percent of height'],
                                'art_note_size'  => ['Bottom line size', 'Percent of width'],
                            ];
                        ?>
                    <div class="sp-idcu-grid">
                        <?php foreach ($sp_art_fields as $sp_key => $sp_field): ?>
                        <div class="sp-idcu-field">
                            <label
                                for="sp_idc_<?php echo esc_attr($sp_key); ?>"><?php echo esc_html($sp_field[0]); ?></label>
                            <div class="sp-idcu-num-input">
                                <input type="number" step="0.01" min="0" max="100"
                                    id="sp_idc_<?php echo esc_attr($sp_key); ?>" name="<?php echo esc_attr($sp_key); ?>"
                                    value="<?php echo esc_attr($settings[$sp_key]); ?>" />
                                <span class="sp-idcu-num-unit">%</span>
                            </div>
                            <p class="description"><?php echo esc_html($sp_field[1]); ?></p>
                        </div>
                        <?php endforeach; ?>

                        <div class="sp-idcu-field">
                            <label for="sp_idc_art_value_color">Value colour</label>
                            <div class="sp-idcu-color-input">
                                <input type="color" id="sp_idc_art_value_color" name="art_value_color"
                                    value="<?php echo esc_attr($settings['art_value_color']); ?>" />
                                <span
                                    class="sp-idcu-color-hex"><?php echo esc_html(strtoupper($settings['art_value_color'])); ?></span>
                            </div>
                            <p class="description">Match the printed labels</p>
                        </div>

                        <div class="sp-idcu-field">
                            <label for="sp_idc_art_note_color">Bottom line colour</label>
                            <div class="sp-idcu-color-input">
                                <input type="color" id="sp_idc_art_note_color" name="art_note_color"
                                    value="<?php echo esc_attr($settings['art_note_color']); ?>" />
                                <span
                                    class="sp-idcu-color-hex"><?php echo esc_html(strtoupper($settings['art_note_color'])); ?></span>
                            </div>
                            <p class="description">Red by default</p>
                        </div>
                    </div>

                    <div class="sp-idcu-field">
                        <label for="sp_idc_bottom_note">Bottom line</label>
                        <input type="text" id="sp_idc_bottom_note" name="bottom_note" maxlength="300"
                            value="<?php echo esc_attr($settings['bottom_note']); ?>"
                            placeholder="Every word of the line, in this one field" />
                        <p class="description">Printed in bold red, centred, just under the address the artwork prints
                            at the bottom. Keep every word of the line in this one field — it wraps if it is longer than
                            the card.</p>
                    </div>

                    <div class="sp-idcu-field">
                        <label>Field row positions</label>
                        <p class="description">Vertical centre of each printed row, as a percent of height. The design
                            bundled with the plugin spaces its rows unevenly, so each one is set separately.</p>
                        <div class="sp-idcu-grid sp-idcu-grid-rows">
                            <?php
                                $sp_row_names = ['Name', "Father's name", 'Emergency no', 'Enrollment no', 'Address'];
                                    $sp_row_tops  = sp_idcard_art_rows();

                                    foreach ($sp_row_names as $sp_index => $sp_row_name):
                                ?>
                            <div class="sp-idcu-field">
                                <label
                                    for="sp_idc_art_row_<?php echo (int) $sp_index; ?>"><?php echo esc_html($sp_row_name); ?></label>
                                <div class="sp-idcu-num-input">
                                    <input type="number" step="0.01" min="0" max="100"
                                        id="sp_idc_art_row_<?php echo (int) $sp_index; ?>" name="art_rows[]"
                                        value="<?php echo esc_attr($sp_row_tops[$sp_index]); ?>" />
                                    <span class="sp-idcu-num-unit">%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="sp-idcu-divider" data-sp-idc-frame-only<?php echo $is_artwork ? ' hidden' : ''; ?>></div>
                <div class="sp-idcu-form-section-title"
                    data-sp-idc-frame-only<?php echo $is_artwork ? ' hidden' : ''; ?>>
                    <span>03</span>
                    <div>
                        <strong>Card text</strong>
                        <small>Header, footer and signature lines. Used by the plain backdrop style.</small>
                    </div>
                </div>

                <div class="sp-idcu-step-body" data-sp-idc-frame-only<?php echo $is_artwork ? ' hidden' : ''; ?>>
                    <div class="sp-idcu-field">
                        <label for="sp_idc_institute">Institute name</label>
                        <input type="text" id="sp_idc_institute" name="institute_name" maxlength="120"
                            value="<?php echo esc_attr($settings['institute_name']); ?>" />
                    </div>

                    <div class="sp-idcu-field">
                        <label for="sp_idc_tagline">Tagline</label>
                        <input type="text" id="sp_idc_tagline" name="tagline" maxlength="180"
                            value="<?php echo esc_attr($settings['tagline']); ?>"
                            placeholder="e.g. &quot;One Platform. Infinite Possibilities&quot;" />
                    </div>

                    <div class="sp-idcu-field">
                        <label for="sp_idc_address">Bottom address</label>
                        <textarea id="sp_idc_address" name="bottom_address" rows="2" maxlength="400"
                            placeholder="Address printed on the bottom strip"><?php echo esc_textarea($settings['bottom_address']); ?></textarea>
                    </div>

                    <div class="sp-idcu-grid sp-idcu-grid-2">
                        <div class="sp-idcu-field">
                            <label for="sp_idc_phone">Bottom phone line</label>
                            <input type="text" id="sp_idc_phone" name="phone_line" maxlength="80"
                                value="<?php echo esc_attr($settings['phone_line']); ?>"
                                placeholder="e.g. +91-9929119729" />
                        </div>

                        <div class="sp-idcu-field">
                            <label for="sp_idc_director">Signature label</label>
                            <input type="text" id="sp_idc_director" name="director_name" maxlength="80"
                                value="<?php echo esc_attr($settings['director_name']); ?>" />
                        </div>
                    </div>

                    <div class="sp-idcu-field">
                        <label for="sp_idc_qrlabel">QR label</label>
                        <input type="text" id="sp_idc_qrlabel" name="qr_label" maxlength="60"
                            value="<?php echo esc_attr($settings['qr_label']); ?>" />
                    </div>
                </div>

                <div class="sp-idcu-form-footer">
                    <button type="submit" class="button button-primary sp-idcu-submit">
                        <span>Save design</span>
                        <span class="sp-idcu-submit-arrow" aria-hidden="true">→</span>
                    </button>
                    <span class="sp-idcu-form-hint">Changes apply instantly to the preview on the right.</span>
                </div>
            </form>
        </section>

        <section class="sp-idcu-panel sp-idcu-panel-preview" aria-labelledby="sp-idcu-preview-title">
            <div class="sp-idcu-panel-heading">
                <div>
                    <span class="sp-idcu-section-kicker">GENERATE</span>
                    <h2 id="sp-idcu-preview-title">Student card</h2>
                    <p>Pick the student, check the card, then download it.</p>
                </div>
                <span class="sp-idcu-heading-icon" aria-hidden="true">▤</span>
            </div>

            <div class="sp-idcu-toolbar">
                <div class="sp-idcu-field sp-idcu-student-field">
                    <label for="sp-idc-student">Student</label>
                    <select id="sp-idc-student" data-sp-idc-select data-sp-search-select
                        data-sp-ss-placeholder="Search students by name or email…"
                        data-sp-ss-empty="No matching students">
                        <?php foreach ($students as $sp_student): ?>
                        <option value="<?php echo esc_attr((int) $sp_student->id); ?>"
                            <?php selected($selected, (int) $sp_student->id); ?>>
                            <?php echo esc_html($sp_student->full_name . ' — ' . $sp_student->email); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span
                    class="sp-idcu-status<?php echo($profile && ! empty($profile->idcard_generated_at)) ? ' is-done' : ''; ?>"
                    data-sp-idc-status
                    data-generated="<?php echo $profile && ! empty($profile->idcard_generated_at) ? esc_attr((string) $profile->idcard_generated_at) : ''; ?>">
                    <span class="sp-idcu-status-dot" aria-hidden="true"></span>
                    <?php echo($profile && ! empty($profile->idcard_generated_at)) ? 'Generated' : 'Not generated'; ?>
                </span>
            </div>

            <div class="sp-idcu-stage" data-sp-idc-stage>
                <?php if ($profile): ?>
                <?php sp_render_id_card($profile); ?>
                <?php else: ?>
                <div class="sp-idcu-empty">
                    <span class="sp-idcu-empty-icon" aria-hidden="true">◑</span>
                    <p>No students yet — create a student account first.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="sp-idcu-actions">
                <button type="button" class="button button-primary sp-idcu-btn-primary" data-sp-idc-jpg>
                    <span class="sp-idcu-btn-icon" aria-hidden="true">▦</span>
                    Download JPG
                </button>
                <button type="button" class="button sp-idcu-btn-secondary" data-sp-idc-pdf>
                    <span class="sp-idcu-btn-icon" aria-hidden="true">▤</span>
                    Download PDF
                </button>
                <span class="sp-idcu-hint">Downloads also mark the card as generated in the student records.</span>
            </div>
        </section>
    </div>

    <footer class="sp-idcu-page-footer">
        <span><span class="sp-idcu-footer-dot" aria-hidden="true"></span> ID cards</span>
        <span>Father's name, address and photo are edited on the Student Accounts screen.</span>
    </footer>
</div>
<?php
}