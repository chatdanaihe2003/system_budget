<div class="top-header d-flex justify-content-between align-items-center py-2 px-3">
    <div><strong>ระบบแผนงานและงบประมาณ</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
    
    <div class="user-info d-flex align-items-center">
        <div class="text-end me-3">
            <span class="d-block fw-bold text-warning" style="font-size: 0.95rem;">
                <i class="fa-solid fa-user-circle me-1"></i>
                <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : (isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Guest'); ?> 
            </span>
            
            <?php 
                $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '-';
                $display_role = $user_role; // สร้างตัวแปรใหม่สำหรับแสดงผล
                
                // เปลี่ยนสี Badge ตามสิทธิ์ใหม่ 4 ระดับ ให้ตรงกับ manage_users.php
                $badge_color = "bg-secondary"; // สีเทา (ค่าเริ่มต้นถ้าไม่ตรงเงื่อนไข)
                $u_role = strtolower(trim($user_role));
                
                if ($u_role === 'admin') {
                    $badge_color = "bg-danger";
                    $display_role = 'Admin';
                } elseif ($u_role === 'การเงิน') {
                    $badge_color = "bg-success";
                } elseif ($u_role === 'แผนงาน') {
                    $badge_color = "bg-info text-dark";
                } elseif ($u_role === 'id user' || $u_role === 'user' || $u_role === 'userทั่วไป') {
                    $badge_color = "bg-primary";
                    $display_role = 'User'; // บังคับให้แสดงข้อความเป็น User แค่คำเดียว
                }
            ?>
            <span class="badge <?php echo $badge_color; ?>" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                สิทธิ์: <?php echo htmlspecialchars($display_role); ?>
            </span>
        </div>
        
        <div class="border-start border-light ps-3 text-center">
            <small class="text-white-50 d-block mb-1" style="font-size: 0.8rem;"><?php echo thai_date(time()); ?></small>
            <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal" class="btn btn-outline-light btn-sm fw-bold" style="border-radius: 6px; font-size: 0.8rem;">
                <i class="fa-solid fa-power-off"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</div>

<div class="sub-header">
    <?php echo isset($page_header) ? $page_header : 'ระบบควบคุมงบประมาณ'; ?>
</div>

<div class="navbar-custom">
    <div class="container-fluid d-flex flex-wrap">
        <a href="index.php" class="nav-link-custom <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">รายการหลัก</a>
        
        <?php 
        // เช็คสิทธิ์เพื่อซ่อน/แสดงเมนู
        $nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
        $is_regular_user = ($nav_role === 'id user' || $nav_role === 'userทั่วไป' || $nav_role === 'user');
        $is_planner = ($nav_role === 'แผนงาน');
        
        // ถ้าไม่ใช่ ID User / userทั่วไป ถึงจะเห็นเมนูตั้งค่าระบบ
        if (!$is_regular_user): 
        ?>
        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['officers.php', 'yearbudget.php', 'plan.php', 'Projectoutcomes.php', 'Activity.php', 'Sourcemoney.php', 'Expensesbudget.php', 'Mainmoney.php', 'Subtypesmoney.php', 'manage_users.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
            <ul class="dropdown-menu shadow-sm">
                
                
                
                <?php 
                // ซ่อนเมนู yearbudget.php ถ้าไม่ใช่ admin 
                if ($nav_role === 'admin'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'yearbudget.php') ? 'active' : ''; ?>" href="yearbudget.php">ปีงบประมาณ</a></li>
                <?php endif; ?>

                
                
                <?php 
                // แสดงเมนู Projectoutcomes.php ให้ แผนงาน หรือ admin 
                if ($nav_role === 'admin' || $nav_role === 'แผนงาน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Projectoutcomes.php') ? 'active' : ''; ?>" href="Projectoutcomes.php">โครงการตามแผนปฎิบัติการ</a></li>
                <?php endif; ?>


                <?php 
                // ซ่อนเมนู Expensesbudget.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Expensesbudget.php') ? 'active' : ''; ?>" href="Expensesbudget.php">งบรายจ่าย</a></li>
                <?php endif; ?>

                
                <?php if(isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item fw-bold text-danger <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php"><i class="fa-solid fa-users-gear me-1"></i> จัดการผู้ใช้งานระบบ</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; // สิ้นสุดการซ่อนเมนูตั้งค่าระบบสำหรับ ID User ?>
        
        <?php 
        // ซ่อนเมนู ตัดยอดงบประมาณโครงการ สำหรับ การเงิน
        if ($nav_role !== 'การเงิน'): 
        ?>
        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Cut off the project budget.php', 'Approve the cut off amount.php', 'Expenses.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ตัดยอดงบประมาณโครงการ</a>
            <ul class="dropdown-menu shadow-sm">
                
                <li><a class="dropdown-item <?php echo ($current_page == 'Cut off the project budget.php') ? 'active' : ''; ?>" href="Cut off the project budget.php">ตัดยอดงบประมาณ</a></li>
                
                <?php 
                // ซ่อนเมนู "อนุมัติการตัดยอด" สำหรับ ID User
                if (!$is_regular_user): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Approve the cut off amount.php') ? 'active' : ''; ?>" href="Approve the cut off amount.php">อนุมัติการตัดยอด</a></li>
                <?php endif; ?>
                
                <li><a class="dropdown-item <?php echo ($current_page == 'Expenses.php') ? 'active' : ''; ?>" href="Expenses.php">ประวัติการตัดยอด</a></li>
            </ul>
        </div>
        <?php endif; // สิ้นสุดการซ่อนเมนู ตัดยอดงบประมาณโครงการ สำหรับ การเงิน ?>

        <?php 
        // แสดงเมนู ทะเบียนขอเบิก/ขอยืมโครงการ (เฉพาะ Admin, การเงิน, User และ แผนงาน)
        if ($nav_role === 'admin' || $nav_role === 'การเงิน' || $is_regular_user || $is_planner): 
        ?>
        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['NewRequestforWithdrawalProjectLoan.php', 'Payment approval history.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนขอเบิก</a>
            <ul class="dropdown-menu shadow-sm">
                
                <?php 
                // ID User และ แผนงาน จะมองไม่เห็นหน้า NewRequestforWithdrawalProjectLoan.php
                if (!$is_regular_user && !$is_planner): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'NewRequestforWithdrawalProjectLoan.php') ? 'active' : ''; ?>" href="NewRequestforWithdrawalProjectLoan.php">ทะเบียนขอเบิก/ขอยืมโครงการ</a></li>
                <?php endif; ?>
                
                <li><a class="dropdown-item <?php echo ($current_page == 'Payment approval history.php') ? 'active' : ''; ?>" href="Payment approval history.php">ประวัติการอนุมัติให้เบิก/ยืม</a></li>
            </ul>
        </div>
        <?php endif; ?>

       
        <div>
        <a href="project report.php" class="nav-link-custom <?php echo ($current_page == 'project report.php') ? 'active' : ''; ?>">รายงานโครงการ</a>
        </div>
        
        
        <a href="Dashboard.php" class="nav-link-custom <?php echo ($current_page == 'Dashboard.php') ? 'active' : ''; ?>">Dashboard</a>

        <div class="dropdown ms-auto">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['point.php', 'Summary of results.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ประเมินระบบ</a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item <?php echo ($current_page == 'point.php') ? 'active' : ''; ?>" href="point.php">
                        ประเมินระบบแผนงานและงบประมาณ
                    </a>
                </li>
                
                <?php 
                // แสดงเมนูนี้เฉพาะผู้ดูแลระบบ (Admin) เท่านั้น
                if ($nav_role === 'admin'): 
                ?>
                <li>
                    <a class="dropdown-item <?php echo ($current_page == 'Summary of results.php') ? 'active' : ''; ?>" href="Summary of results.php">
                        สรุปผลการประเมินระบบแผนงานและงบประมาณ
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
    </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-right-from-bracket me-2"></i> ยืนยันการออกจากระบบ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-arrow-right-from-bracket text-danger mb-3" style="font-size: 4.5rem;"></i>
                <h4 class="fw-bold text-dark mb-2">คุณต้องการออกจากระบบใช่หรือไม่?</h4>
                <p class="text-muted mb-0 fs-5">กรุณากดยืนยันเพื่อกลับสู่หน้าเข้าสู่ระบบ</p>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center py-3" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
                <a href="Logout.php" class="btn btn-danger px-4 fw-bold" style="border-radius: 8px;">
                    <i class="fa-solid fa-check me-1"></i> ยืนยันออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</div>