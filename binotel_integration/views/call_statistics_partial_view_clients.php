<?php if (!empty($call_statistics)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Тип дзвінка</th>
                    <th>Час дзвінка</th>
                    <th>Запис дзвінка</th>
                    <th>Транскрипція</th>
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
                            <?php if ($call['recording_link']): ?>
                                <div class="binotel-transcription-wrapper" data-call-id="<?php echo $call['id']; ?>" data-call-type="clients" data-recording-url="<?php echo htmlspecialchars($call['recording_link']); ?>">
                                    <?php if (!empty($call['transcription'])): ?>
                                        <div class="binotel-transcription-text"><?php echo htmlspecialchars($call['transcription']); ?></div>
                                        <button class="btn btn-xs btn-default binotel-retranscribe-btn" title="Транскрибувати повторно">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-xs btn-primary binotel-transcribe-btn">
                                            <i class="fa fa-file-text-o"></i> Транскрибувати
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                &mdash;
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

<?php echo binotel_transcription_js(); ?>
