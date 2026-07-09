<?php
// Copyright (c) 2026 Andreas Vetter
// Wiederverwendbare Zeilen-Markup für eine Spielerfrage — genutzt sowohl von
// admin/messages.php (initiale Liste) als auch von api/messages.php
// (get_new_messages) fürs Live-Nachladen neuer Fragen ohne Reload.

if (!function_exists('render_message_row')) {
function render_message_row(array $msg): string {
    // Feedback-Einträge (bug/wish/feedback) teilen sich das Markup mit den
    // Spielerfragen: statt Beantwortet-Logik zählt bei ihnen der Status.
    $type       = $msg['type']   ?? 'question';
    $status     = $msg['status'] ?? 'open';
    $isFeedback = ($type !== 'question');
    $typeMeta   = feedbackTypeMeta()[$type]     ?? feedbackTypeMeta()['question'];
    $statusMeta = feedbackStatusMeta()[$status] ?? feedbackStatusMeta()['open'];
    $hasReply   = ($msg['reply'] !== null);
    // "Neu"/aufgeklappt: Fragen ohne Antwort — bzw. Feedback, das noch offen ist
    $isNew = $isFeedback ? ($status === 'open') : !$hasReply;
    ob_start();
    ?>
    <div class="panel msg-panel" id="msg-<?= (int)$msg['id'] ?>" data-type="<?= e($type) ?>"
         style="padding:.9rem 1rem<?= $isNew ? ';border-left:3px solid var(--accent)' : ';opacity:.75' ?>">

      <!-- Kopfzeile: Name + Zeit + Badge + Aktionen + Chevron -->
      <div class="flex-between" style="flex-wrap:wrap;gap:.4rem;<?= !$isNew ? 'cursor:pointer' : '' ?>"
           <?= !$isNew ? 'onclick="toggleCollapse(' . (int)$msg['id'] . ')" title="Auf-/Zuklappen"' : '' ?>>
        <div class="flex gap-sm" style="align-items:center;flex-wrap:wrap">
          <span style="font-family:var(--font-display);font-size:.92rem;color:var(--text-bright)">
            <?= e($msg['display_name']) ?>
          </span>
          <span class="text-dim text-xs">
            <?= e(date('d.m.Y H:i', strtotime($msg['created_at']))) ?>
          </span>
          <?php if ($isFeedback): ?>
            <span class="tag tag--night" style="font-size:.65rem"><?= $typeMeta['icon'] ?> <?= e($typeMeta['label']) ?></span>
            <span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)"><?= $statusMeta['icon'] ?> <?= e($statusMeta['label']) ?></span>
          <?php elseif ($isNew): ?>
            <span class="tag tag--running" style="font-size:.65rem">Neu</span>
          <?php else: ?>
            <span class="tag" style="font-size:.65rem;background:var(--panel-bg);color:var(--text-dim)">✓ Beantwortet</span>
          <?php endif; ?>
          <?php if ($msg['published']): ?>
            <span class="tag tag--alive" style="font-size:.65rem" id="pub-tag-<?= (int)$msg['id'] ?>">📢 Im FAQ</span>
          <?php else: ?>
            <span id="pub-tag-<?= (int)$msg['id'] ?>" style="display:none" class="tag tag--alive" style="font-size:.65rem">📢 Im FAQ</span>
          <?php endif; ?>
          <?php if (!empty($msg['voice_path'])): ?>
            <span class="tag tag--night" style="font-size:.65rem">🎙️ Sprachnachricht</span>
          <?php endif; ?>
          <!-- Vorschau der Frage wenn zugeklappt -->
          <?php if (!$isNew): ?>
          <span class="msg-preview text-dim" id="preview-<?= (int)$msg['id'] ?>"
                style="font-size:.8rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= e(mb_substr($msg['message'], 0, 80)) ?>
          </span>
          <?php endif; ?>
        </div>
        <div class="flex gap-xs" onclick="event.stopPropagation()">
          <?php if ($isFeedback): ?>
          <select class="form-input" style="width:auto;font-size:.75rem;padding:.2rem .4rem"
                  title="Bearbeitungsstatus setzen"
                  onchange="setStatus(<?= (int)$msg['id'] ?>, this.value)">
            <?php foreach (feedbackStatusMeta() as $sKey => $sMeta): ?>
            <option value="<?= e($sKey) ?>" <?= $sKey === $status ? 'selected' : '' ?>><?= $sMeta['icon'] ?> <?= e($sMeta['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($msg['voice_path']) && VOICE_TRANSCRIPTION): ?>
          <button class="btn btn--ghost btn--sm" title="Sprachnachricht automatisch transkribieren (OpenAI)"
                  onclick="transcribeVoice(<?= (int)$msg['id'] ?>, this)">🎙️→📝 Transkribieren</button>
          <?php endif; ?>
          <?php elseif (!$isNew): ?>
          <button class="btn btn--ghost btn--sm" id="pub-btn-<?= (int)$msg['id'] ?>"
                  title="<?= $msg['published'] ? 'Aus FAQ entfernen' : (!empty($msg['voice_path']) && empty($msg['faq_question']) ? 'Vor Veröffentlichung erst FAQ-Text hinterlegen' : 'Als FAQ veröffentlichen') ?>"
                  onclick="togglePublish(<?= (int)$msg['id'] ?>)">
            <?= $msg['published'] ? '📢 Veröffentlicht' : '📢 FAQ freigeben' ?>
          </button>
          <button class="btn btn--ghost btn--sm" title="FAQ-Text anonymisieren/bearbeiten"
                  onclick="toggleFaqEdit(<?= (int)$msg['id'] ?>)">✏️ FAQ-Text</button>
          <?php if (!empty($msg['voice_path']) && VOICE_TRANSCRIPTION): ?>
          <button class="btn btn--ghost btn--sm" title="Sprachnachricht automatisch transkribieren (OpenAI)"
                  onclick="transcribeVoice(<?= (int)$msg['id'] ?>, this)">🎙️→📝 Transkribieren</button>
          <?php endif; ?>
          <?php endif; ?>
          <button class="btn btn--ghost btn--sm" title="Löschen"
                  onclick="deleteMsg(<?= (int)$msg['id'] ?>)">🗑</button>
          <?php if (!$isNew): ?>
          <span class="msg-chevron" id="chevron-<?= (int)$msg['id'] ?>"
                style="font-size:.8rem;color:var(--text-dim);padding:.1rem .2rem;line-height:1;transition:transform .2s;display:inline-block;transform:rotate(0deg)">▼</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Aufklappbarer Inhalt -->
      <div id="body-<?= (int)$msg['id'] ?>"
           style="<?= !$isNew ? 'display:none;' : '' ?>margin-top:.75rem">

        <!-- Frage des Spielers (Original, wird nie verändert) -->
        <div style="background:var(--panel-bg);border:1px solid var(--border);border-radius:8px;
                    padding:.6rem .85rem;margin-bottom:.6rem;font-size:.9rem;line-height:1.5">
          <?php if (!empty($msg['voice_path'])): ?>
            <?php if (is_file(ROOT_PATH . '/' . $msg['voice_path'])): ?>
              <?php // onerror fängt beschädigte/nicht dekodierbare Aufnahmen ab —
                    // Player ausblenden, Hinweis daneben einblenden ?>
              <audio controls preload="none" style="width:100%;max-width:340px;display:block"
                     src="<?= e(API_URL) ?>/messages.php?action=voice_file&amp;id=<?= (int)$msg['id'] ?>"
                     onerror="this.style.display='none';this.nextElementSibling.style.display=''"></audio>
              <span class="text-dim text-sm" style="display:none">⚠️ Aufnahme kann nicht abgespielt werden (Datei beschädigt oder Format nicht unterstützt).</span>
            <?php else: ?>
              <span class="text-dim text-sm">⚠️ Aufnahme-Datei fehlt auf dem Server.</span>
            <?php endif; ?>
            <?php // Bei Sprach-Feedback: Transkript direkt unter dem Player zeigen
                  // (faq_question dient hier als Transkript-Feld — FAQ gibt es für Feedback nicht) ?>
            <?php if ($isFeedback && !empty($msg['faq_question'])): ?>
              <p class="text-dim text-sm" style="margin:.5rem 0 0;line-height:1.5">📝 <?= e($msg['faq_question']) ?></p>
            <?php endif; ?>
          <?php else: ?>
            <?= e($msg['message']) ?>
          <?php endif; ?>
        </div>

        <!-- FAQ-Text bearbeiten (anonymisierte/gekürzte Version für die öffentliche FAQ).
             Bei Sprachnachrichten ist das die EINZIGE Möglichkeit, Inhalte zu veröffentlichen —
             die Audiodatei selbst wird nie öffentlich, nur diese vom Spielleiter
             geschriebene Textfassung. -->
        <?php if (!$isNew && !$isFeedback): ?>
        <div id="faq-edit-<?= (int)$msg['id'] ?>" style="display:none;margin-bottom:.6rem">
          <label class="text-dim text-xs" style="display:block;margin-bottom:.25rem">
            Text für die öffentliche FAQ (Namen/persönliche Angaben hier entfernen):
          </label>
          <textarea class="form-input" id="faq-text-<?= (int)$msg['id'] ?>" rows="2" maxlength="500"
                    placeholder="<?= !empty($msg['voice_path']) ? 'Anonymisierte Textfassung der Sprachnachricht …' : '' ?>"
                    style="width:100%;font-size:.85rem;resize:vertical"><?= e($msg['faq_question'] ?? (!empty($msg['voice_path']) ? '' : $msg['message'])) ?></textarea>
          <div class="flex gap-xs mt-1">
            <button class="btn btn--primary btn--sm" onclick="saveFaqQuestion(<?= (int)$msg['id'] ?>)">✓ Speichern</button>
            <button class="btn btn--ghost btn--sm" onclick="toggleFaqEdit(<?= (int)$msg['id'] ?>)">Abbrechen</button>
          </div>
          <div id="faq-edit-result-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.4rem"></div>
        </div>
        <?php endif; ?>

        <!-- Antwort-Bereich (Anzeige wenn Antwort existiert, sonst Formular —
             bei Feedback ist eine Antwort optional, der Status zählt) -->
        <div id="reply-display-<?= (int)$msg['id'] ?>"
             style="<?= !$hasReply ? 'display:none' : '' ?>">
          <?php if ($msg['reply']): ?>
          <div style="background:var(--input-bg,var(--card-bg));border-left:3px solid var(--accent-border);
                      border-radius:0 8px 8px 0;padding:.6rem .85rem;font-size:.88rem;line-height:1.5">
            <div class="text-dim text-xs mb-1">
              Deine Antwort &middot; <?= e(date('d.m.Y H:i', strtotime($msg['replied_at']))) ?>
            </div>
            <p style="margin:0;color:var(--text-bright)" id="reply-text-display-<?= (int)$msg['id'] ?>">
              <?= e($msg['reply']) ?>
            </p>
            <?php if (!empty($msg['reply_voice_path'])): ?>
            <audio controls preload="none" style="width:100%;max-width:320px;margin-top:.4rem;display:block"
                   src="<?= e(API_URL) ?>/messages.php?action=voice_file&amp;which=reply&amp;id=<?= (int)$msg['id'] ?>"></audio>
            <?php endif; ?>
            <button class="btn btn--ghost btn--sm mt-1"
                    onclick="openEditReply(<?= (int)$msg['id'] ?>, <?= htmlspecialchars(json_encode($msg['reply']), ENT_QUOTES) ?>)">
              ✏️ Bearbeiten
            </button>
          </div>
          <?php endif; ?>
        </div>

        <div id="reply-form-<?= (int)$msg['id'] ?>"
             style="<?= !$hasReply ? '' : 'display:none' ?>">
          <textarea class="form-input"
                    id="reply-text-<?= (int)$msg['id'] ?>"
                    placeholder="Antwort eingeben …" rows="2"
                    style="width:100%;font-size:.85rem;resize:vertical;margin-bottom:.4rem"
                    maxlength="1000"></textarea>
          <div class="flex gap-xs">
            <button class="btn btn--primary btn--sm"
                    onclick="sendReply(<?= (int)$msg['id'] ?>)">✓ Antworten</button>
            <?php if ($hasReply): ?>
            <button class="btn btn--ghost btn--sm"
                    onclick="cancelEdit(<?= (int)$msg['id'] ?>)">Abbrechen</button>
            <?php endif; ?>
          </div>
          <div id="reply-result-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.4rem"></div>

          <?php if (VOICE_MESSAGES): ?>
          <!-- Sprachantwort aufnehmen (MediaRecorder, max. 1 Min.) -->
          <div style="margin-top:.5rem;border-top:1px dashed var(--border);padding-top:.5rem">
            <button class="btn btn--ghost btn--sm" type="button"
                    onclick="rvOpen(<?= (int)$msg['id'] ?>, this)">🎙️ Per Sprache antworten</button>
            <div id="rv-box-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.4rem">
              <div class="flex gap-xs" style="align-items:center;flex-wrap:wrap">
                <button class="btn btn--ghost btn--sm" type="button"
                        id="rv-rec-<?= (int)$msg['id'] ?>" onclick="rvToggleRec(<?= (int)$msg['id'] ?>)">🔴 Aufnahme starten</button>
                <span id="rv-timer-<?= (int)$msg['id'] ?>" class="text-dim text-xs" style="display:none">0:00 / 1:00</span>
                <button class="btn btn--primary btn--sm" type="button"
                        id="rv-send-<?= (int)$msg['id'] ?>" onclick="rvSend(<?= (int)$msg['id'] ?>)" disabled>✓ Sprachantwort senden</button>
              </div>
              <audio id="rv-preview-<?= (int)$msg['id'] ?>" controls style="display:none;width:100%;max-width:320px;margin-top:.3rem"></audio>
              <div id="rv-result-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.3rem"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /body -->

    </div>
    <?php
    return trim(ob_get_clean());
}
}
