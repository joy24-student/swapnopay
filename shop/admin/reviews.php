<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<section class="content-header">
    <div class="content-header-left">
        <h1>Product Reviews</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body table-responsive">
                    <?php
                    // Display success or error messages from actions like approve/delete
                    if(isset($_SESSION['success_message'])) {
                        echo "<div class='success' style='padding: 10px;background:#ebffeb;margin-bottom:20px;'>".$_SESSION['success_message']."</div>";
                        unset($_SESSION['success_message']);
                    }
                    if(isset($_SESSION['error_message'])) {
                        echo "<div class='error' style='padding: 10px;background:#ffebeb;margin-bottom:20px;'>".$_SESSION['error_message']."</div>";
                        unset($_SESSION['error_message']);
                    }
                    ?>
                    <table id="example1" class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Rating</th>
                                <th>Title</th>
                                <th>Comment</th> <!-- Added Comment column for direct view -->
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i=0;
                            // Join tbl_review with tbl_product and tbl_customer to get names
                            $statement = $pdo->prepare("SELECT r.*, p.p_name, c.cust_name
                                FROM tbl_review r JOIN tbl_product p ON r.product_id = p.p_id JOIN tbl_customer c ON r.cust_id = c.cust_id ORDER BY r.created_at DESC"); // CHANGED FROM tbl_rating
                            $statement->execute();
                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($result as $row) {
                                $i++;
                                ?>
                                <tr id="review-row-<?php echo $row['id']; ?>">
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo htmlspecialchars($row['p_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cust_name']); ?></td>
                                    <td>
                                        <?php
                                        for($star=1;$star<=5;$star++) {
                                            if($star <= $row['rating']) {
                                                echo '<i class="fa fa-star" style="color:#f1c40f;"></i>';
                                            } else {
                                                echo '<i class="fa fa-star-o" style="color:#f1c40f;"></i>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['review_title']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars(substr($row['comment'], 0, 100))); ?>...</td> <!-- Show snippet -->
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                                    <td class="cell-review-status" id="review-status-cell-<?php echo $row['id']; ?>">
                                        <span class="badge <?php echo ($row['status'] == 'Approved') ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#viewReviewModal<?php echo $row['id']; ?>">
                                            View
                                        </button>
                                        <a href="review-approve.php?id=<?php echo $row['id']; ?>"
                                           class="btn btn-<?php echo ($row['status'] == 'Approved') ? 'warning' : 'success'; ?> btn-xs js-review-toggle-status"
                                           data-id="<?php echo $row['id']; ?>">
                                            <?php echo ($row['status'] == 'Approved') ? 'Unapprove' : 'Approve'; ?>
                                        </a>
                                        <a href="#" class="btn btn-danger btn-xs"
                                           data-href="review-delete.php?id=<?php echo $row['id']; ?>"
                                           data-toggle="modal" data-target="#confirm-delete">
                                           Delete
                                        </a>
                                    </td>
                                </tr>

                                <!-- View Review Modal -->
                                <div class="modal fade" id="viewReviewModal<?php echo $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewReviewModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewReviewModalLabel">Review Details for "<?php echo htmlspecialchars($row['p_name']); ?>"</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($row['cust_name']); ?></p>
                                                <p><strong>Rating:</strong>
                                                    <?php for($star=1;$star<=5;$star++): ?>
                                                        <?php if($star <= $row['rating']): ?>
                                                            <i class="fa fa-star" style="color:#f1c40f;"></i>
                                                        <?php else: ?>
                                                            <i class="fa fa-star-o" style="color:#f1c40f;"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </p>
                                                <p><strong>Title:</strong> <?php echo htmlspecialchars($row['review_title']); ?></p>
                                                <p><strong>Comment:</strong><br><?php echo nl2br(htmlspecialchars($row['comment'])); ?></p>
                                                <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($row['created_at'])); ?></p>
                                                <p><strong>Current Status:</strong> <span class="badge <?php echo ($row['status'] == 'Approved') ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($row['status']); ?></span></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <a href="review-approve.php?id=<?php echo $row['id']; ?>"
                                                   class="btn btn-<?php echo ($row['status'] == 'Approved') ? 'warning' : 'success'; ?> js-review-toggle-status"
                                                   data-id="<?php echo $row['id']; ?>">
                                                    <?php echo ($row['status'] == 'Approved') ? 'Unapprove' : 'Approve'; ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Delete Confirmation Modal (Shared) -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Delete Confirmation</h4>
            </div>
            <div class="modal-body">
                Sure you want to delete this item?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>