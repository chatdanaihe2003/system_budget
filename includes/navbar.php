<div class="top-header d-flex justify-content-between align-items-center py-2 px-3">
    <div><strong>Budget control system</strong> สำนักงานเขตพื้นที่การศึกษาประถมศึกษาชลบุรี เขต 2</div>
    
    <div class="user-info d-flex align-items-center">
        <div class="text-end me-3">
            <span class="d-block fw-bold text-warning" style="font-size: 0.95rem;">
                <i class="fa-solid fa-user-circle me-1"></i>
                <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : (isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Guest'); ?> 
            </span>
            
            <?php 
                $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '-';
                
                // เปลี่ยนสี Badge ตามสิทธิ์ใหม่ 4 ระดับ ให้ตรงกับ manage_users.php
                $badge_color = "bg-secondary"; // สีเทา (ค่าเริ่มต้นถ้าไม่ตรงเงื่อนไข)
                $u_role = strtolower(trim($user_role));
                
                if ($u_role === 'admin') {
                    $badge_color = "bg-danger";
                } elseif ($u_role === 'การเงิน') {
                    $badge_color = "bg-success";
                } elseif ($u_role === 'แผนงาน') {
                    $badge_color = "bg-info text-dark";
                } elseif ($u_role === 'id user' || $u_role === 'user') {
                    $badge_color = "bg-primary";
                }
            ?>
            <span class="badge <?php echo $badge_color; ?>" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                สิทธิ์: <?php echo htmlspecialchars($user_role); ?>
            </span>
        </div>
        
        <div class="border-start border-light ps-3 text-center">
            <small class="text-white-50 d-block mb-1" style="font-size: 0.8rem;"><?php echo thai_date(time()); ?></small>
            <a href="Logout.php" class="btn btn-outline-light btn-sm fw-bold" onclick="return confirm('ยืนยันออกจากระบบ?');" style="border-radius: 6px; font-size: 0.8rem;">
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
        // ซ่อนเมนู "ตั้งค่าระบบ" สำหรับผู้ใช้ที่เป็น ID User หรือ user ทั่วไป
        $nav_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
        if ($nav_role !== 'id user' && $nav_role !== 'userทั่วไป' && $nav_role !== 'user'): 
        ?>
        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['officers.php', 'yearbudget.php', 'plan.php', 'Projectoutcomes.php', 'Activity.php', 'Sourcemoney.php', 'Expensesbudget.php', 'Mainmoney.php', 'Subtypesmoney.php', 'manage_users.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ตั้งค่าระบบ</a>
            <ul class="dropdown-menu shadow-sm">
                
                <?php 
                // ซ่อนเมนู officers.php ถ้าไม่ใช่ admin 
                if ($nav_role === 'admin'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'officers.php') ? 'active' : ''; ?>" href="officers.php">เจ้าหน้าที่การเงินฯ</a></li>
                <?php endif; ?>
                
                <?php 
                // ซ่อนเมนู yearbudget.php ถ้าไม่ใช่ admin 
                if ($nav_role === 'admin'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'yearbudget.php') ? 'active' : ''; ?>" href="yearbudget.php">ปีงบประมาณ</a></li>
                <?php endif; ?>

                <?php 
                // ซ่อนเมนู plan.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'plan.php') ? 'active' : ''; ?>" href="plan.php">แผนงาน</a></li>
                <?php endif; ?>
                <?php 
                // ซ่อนเมนู Projectoutcomes.php ถ้าไม่ใช่ admin หรือ แผนงาน
                if ($nav_role === 'admin' || $nav_role === 'แผนงาน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Projectoutcomes.php') ? 'active' : ''; ?>" href="Projectoutcomes.php">โครงการตามแผนปฎิบัติการ</a></li>
                <?php endif; ?>

                <?php 
                // ซ่อนเมนู Activity.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Activity.php') ? 'active' : ''; ?>" href="Activity.php">กิจกรรมหลัก</a></li>
                <?php endif; ?>

                <?php 
                // ซ่อนเมนู Sourcemoney.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Sourcemoney.php') ? 'active' : ''; ?>" href="Sourcemoney.php">แหล่งของเงิน</a></li>
                <?php endif; ?>

                <?php 
                // ซ่อนเมนู Expensesbudget.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Expensesbudget.php') ? 'active' : ''; ?>" href="Expensesbudget.php">งบรายจ่าย</a></li>
                <?php endif; ?>

                <?php 
                // ซ่อนเมนู Mainmoney.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Mainmoney.php') ? 'active' : ''; ?>" href="Mainmoney.php">ประเภท(หลัก)ของเงิน</a></li>
                <?php endif; ?>

                 <?php 
                // ซ่อนเมนู Subtypesmoney.php ถ้าไม่ใช่ admin หรือ การเงิน
                if ($nav_role === 'admin' || $nav_role === 'การเงิน'): 
                ?>
                <li><a class="dropdown-item <?php echo ($current_page == 'Subtypesmoney.php') ? 'active' : ''; ?>" href="Subtypesmoney.php">ประเภท(ย่อย)ของเงิน</a></li>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item fw-bold text-danger <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php"><i class="fa-solid fa-users-gear me-1"></i> จัดการผู้ใช้งานระบบ</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Budgetallocation.php', 'Receivebudget.php', 'Receiveoffbudget.php', 'Receivenational.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนรับ</a>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item <?php echo ($current_page == 'Budgetallocation.php') ? 'active' : ''; ?>" href="Budgetallocation.php">รับการจัดสรรงบประมาณ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Receivebudget.php') ? 'active' : ''; ?>" href="Receivebudget.php">รับเงินงบประมาณ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Receiveoffbudget.php') ? 'active' : ''; ?>" href="Receiveoffbudget.php">รับเงินนอกงบประมาณ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Receivenational.php') ? 'active' : ''; ?>" href="Receivenational.php">รับเงินรายได้แผ่นดิน</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['RequestforWithdrawalProjectLoan.php', 'ProjectRefundRegistration.php', 'TreasuryWithdrawal.php', 'TreasuryRefundRegister.php', 'Withdrawtheappeal.php', 'Fundrolloverregister.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนขอเบิก</a>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item <?php echo ($current_page == 'RequestforWithdrawalProjectLoan.php') ? 'active' : ''; ?>" href="RequestforWithdrawalProjectLoan.php">ทะเบียนขอเบิก/ขอยืมเงินโครงการ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'ProjectRefundRegistration.php') ? 'active' : ''; ?>" href="ProjectRefundRegistration.php">***ทะเบียนคืนเงินโครงการ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'TreasuryWithdrawal.php') ? 'active' : ''; ?>" href="TreasuryWithdrawal.php">ทะเบียนขอเบิกเงินคงคลัง</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'TreasuryRefundRegister.php') ? 'active' : ''; ?>" href="TreasuryRefundRegister.php">***ทะเบียนคืนเงินคงคลัง</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Withdrawtheappeal.php') ? 'active' : ''; ?>" href="Withdrawtheappeal.php">***ยกเลิกฎีกา</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Fundrolloverregister.php') ? 'active' : ''; ?>" href="Fundrolloverregister.php">ทะเบียนเงินกันเหลื่อมปี</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle <?php echo (in_array($current_page, ['Authorizebudgetexpenditures.php', 'Orderpaymentoutsidethebudget.php', 'Orderpaymentofstaterevenue.php', 'Governmentadvancefunds.php', 'Approvedformaintypepayment.php', 'Approved for governmentadvancepayment.php', 'Major type of payment.php', 'Advance payment for government service.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">ทะเบียนจ่าย</a>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item <?php echo ($current_page == 'Authorizebudgetexpenditures.php') ? 'active' : ''; ?>" href="Authorizebudgetexpenditures.php">สั่งจ่ายเงินงบประมาณ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Orderpaymentoutsidethebudget.php') ? 'active' : ''; ?>" href="Orderpaymentoutsidethebudget.php">สั่งจ่ายเงินนอกงบประมาณ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Orderpaymentofstaterevenue.php') ? 'active' : ''; ?>" href="Orderpaymentofstaterevenue.php">สั่งจ่ายเงินรายได้แผ่นดิน</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Governmentadvancefunds.php') ? 'active' : ''; ?>" href="Governmentadvancefunds.php">เงินทดรองราชการ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Approvedformaintypepayment.php') ? 'active' : ''; ?>" href="Approvedformaintypepayment.php">อนุมัติจ่ายเงินประเภทหลัก</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Approved for governmentadvancepayment.php') ? 'active' : ''; ?>" href="Approved for governmentadvancepayment.php">อนุมัติจ่ายเงินทดรองราชการ</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Major type of payment.php') ? 'active' : ''; ?>" href="Major type of payment.php">จ่ายเงินประเภทหลัก</a></li>
                <li><a class="dropdown-item <?php echo ($current_page == 'Advance payment for government service.php') ? 'active' : ''; ?>" href="Advance payment for government service.php">จ่ายเงินทดรองราชการ</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">ตรวจสอบ</a>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="Check budget allocation.php">ตรวจสอบการจัดสรรงบประมาณ</a></li>
                <li><a class="dropdown-item" href="Check the periodic financial report.php">รายงานเงินประจำงวด</a></li>
                <li><a class="dropdown-item" href="Check main payment type.php">จ่ายเงินประเภทหลัก</a></li>
                <li><a class="dropdown-item" href="Check the government advance payment.php">จ่ายเงินทดรองราชการ</a></li>
                <li><a class="dropdown-item" href="The appeal number does not exist in the system.php">เลขที่ฎีกาที่ไม่มีในระบบ</a></li>
                <li><a class="dropdown-item" href="Appeals regarding project termination classified by invoice.php">ฎีกากับการตัดโครงการจำแนกตามใบงวด</a></li>
                <li><a class="dropdown-item" href="Supreme Court Rulings and References for Reimbursement Requests Classified by Ruling.php">ฎีกากับการอ้างอิงการขอเบิกจำแนกตามฎีกา</a></li>
                <li><a class="dropdown-item" href="Withdrawal requests that have not yet been submitted for approval.php">รายการขอเบิกฯที่ยังไม่ได้วางฎีกา</a></li>
                <li><a class="dropdown-item" href="Requisition items with incorrect installment vouchers.php">รายการขอเบิกฯที่วางฎีกาผิดใบงวด</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <a href="#" class="nav-link-custom dropdown-toggle" data-bs-toggle="dropdown">รายงาน</a>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="Budget allocation report.php">รายงานการจัดสรรงบประมาณ</a></li>
                <li><a class="dropdown-item" href="Expenditure report categorized by project.php">รายงานการใช้จ่ายจำแนกตามโครงการ</a></li>
                <li><a class="dropdown-item" href="Annuity register.php">ทะเบียนเงินงวด</a></li>
                <li><a class="dropdown-item" href="Expenditure report categorized by budget code.php">รายงานการใช้จ่ายจำแนกตามรหัสงบประมาณ</a></li>
                <li><a class="dropdown-item" href="Expenditure report categorized by type of.php">รายงานการใช้จ่ายจำแนกตามประเภทรายการจ่าย</a></li>
                <li><a class="dropdown-item" href="Daily cash balance report.php">รายงานเงินคงเหลือประจำวัน</a></li>
                <li><a class="dropdown-item" href="cash book.php">สมุดเงินสด</a></li>
                <li><a class="dropdown-item" href="budget report.php">รายงานเงินงบประมาณ</a></li>
                <li><a class="dropdown-item" href="Report money outside the budget.php">รายงานเงินนอกงบประมาณ</a></li>
                <li><a class="dropdown-item" href="State income report.php">รายงานเงินรายได้แผ่นดิน</a></li>
                <li><a class="dropdown-item" href="Loan Report.php">รายงานลูกหนี้เงินยืม</a></li>
            </ul>
        </div>
        <div class="dropdown">
            <a href="Cut off the project budget.php" class="nav-link-custom ms-auto">ตัดยอดงบประมาณโครงการ</a>
        </div>
        <a href="#" class="nav-link-custom ms-auto">คู่มือ</a>
    </div>
</div>