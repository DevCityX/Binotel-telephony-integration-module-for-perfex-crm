<?php if (!empty($call_statistics)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Тип дзвінка</th>
                    <th>Час дзвінка</th>
                    <th>Запис дзвінка</th>
                        <th>Транскрибація</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($call_statistics as $call): ?>
                    <tr class="<?php echo $call['call_type'] == '1' ? ($call['recording_link'] ? 'outgoing-call' : 'missed-outgoing-call') : ($call['recording_link'] ? 'incoming-call' : 'missed-incoming-call'); ?>">
                        <td>
                            <div>
                                <?php if ($call['call_type'] == '1'): ?>
                                    <i class="fas fa-long-arrow-alt-up" style="color: <?php echo $call['recording_link'] ? 'blue' : 'red'; ?>;"></i>
                                    <div class="arrow-text-separator"></div>
                                    <?php echo $call['recording_link'] ? 'Вихідний' : 'Вихідний неприйнятий'; ?>
                                <?php else: ?>
                                    <i class="fas fa-long-arrow-alt-down" style="color: <?php echo $call['recording_link'] ? 'green' : 'red'; ?>;"></i>
                                    <div class="arrow-text-separator"></div>
                                    <?php echo $call['recording_link'] ? 'Вхідний' : 'Вхідний неприйнятий'; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo $call['call_time']; ?></td>
                        <td>
                            <?php if ($call['recording_link']): ?>
                                <audio controls style="margin: 0; padding: 0;">
                                    <source src="<?php echo $call['recording_link']; ?>" type="audio/mpeg">
                                    Ваш браузер не підтримує елемент <code>audio</code>.
                                </audio>
                            <?php else: ?>
                                Немає запису
                            <?php endif; ?>
                        </td>
                         <td>
                            <?php if (!empty($call['recording_link'])): ?>
                                <button class="btn btn-default btn-sm js-transcribe-call"
                                        data-call-id="<?php echo (int) $call['id']; ?>"
                                        data-entity-type="client">
                                    Розкласти в текст
                                </button>
                                <div class="text-muted small js-transcribe-status" style="margin-top:6px;">
                                    <?php if (!empty($call['transcribed_at'])): ?>
                                        Оновлено: <?php echo $call['transcribed_at']; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="js-transcribe-result" style="margin-top:8px; white-space: pre-line;">
                                    <?php echo !empty($call['transcription_text']) ? nl2br(htmlspecialchars($call['transcription_text'])) : ''; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>Записів розмов за цей період не знайдено.</p>
<?php endif; ?>


<script>
(function() {
    function bindTranscribeButtons(scope) {
        var buttons = (scope || document).querySelectorAll('.js-transcribe-call');
        buttons.forEach(function(btn) {
            if (btn.dataset.bound === '1') {
                return;
            }
            btn.dataset.bound = '1';
            btn.addEventListener('click', function() {
                var row = btn.closest('td');
                var statusEl = row.querySelector('.js-transcribe-status');
                var resultEl = row.querySelector('.js-transcribe-result');
                btn.disabled = true;
                statusEl.textContent = 'Триває транскрибація...';

                $.post(admin_url + 'binotel_integration/transcribe_call', {
                    call_id: btn.dataset.callId,
                    entity_type: btn.dataset.entityType
                }).done(function(response) {
                    var data = response;
                    if (typeof response === 'string') {
                        data = JSON.parse(response);
                    }
                    if (data.status === 'success') {
                        statusEl.textContent = 'Готово: ' + (data.transcribed_at || 'щойно');
                        resultEl.textContent = data.text || '';
                    } else {
                        statusEl.textContent = data.message || 'Помилка транскрибації';
                    }
                }).fail(function(xhr) {
                    statusEl.textContent = 'Помилка запиту: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : xhr.statusText);
                }).always(function() {
                    btn.disabled = false;
                });
            });
        });
    }

    bindTranscribeButtons(document);
})();
</script>
