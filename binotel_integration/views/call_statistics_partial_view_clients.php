<?php if (!empty($call_statistics)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Тип дзвінка</th>
                    <th>Час дзвінка</th>
                    <th>Запис дзвінка</th>
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>Записів розмов за цей період не знайдено.</p>
<?php endif; ?>
