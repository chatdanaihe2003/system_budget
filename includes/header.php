    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo isset($page_title) ? $page_title : 'AMSS++'; ?></title>
        
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <style>
            /* Modern & Clean Palette */
            :root {
                --primary-dark: #0f172a;    /* Navy Dark */
                --primary-main: #1e293b;    /* Slate Dark */
                --accent-color: #06b6d4;    /* Modern Cyan */
                --accent-hover: #0891b2;
                --bg-body: #f1f5f9;         /* Light Blue Gray */
                --white: #ffffff;
                --text-main: #334155;
                --text-muted: #64748b;
                --transition: all 0.3s ease;
            }

            body { 
                font-family: 'Sarabun', sans-serif; 
                background-color: var(--bg-body); 
                color: var(--text-main); 
            }
            
            /* Top Header */
            .top-header { 
                background-color: var(--primary-dark); 
                color: white; 
                padding: 12px 25px; 
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            .user-info { font-size: 0.85rem; text-align: right; }
            .user-role { 
                background-color: var(--accent-color); 
                color: var(--primary-dark);
                padding: 2px 10px;
                border-radius: 20px;
                font-weight: 700; 
                text-transform: uppercase; 
                font-size: 0.75rem;
                margin: 0 5px;
            }
            
            .btn-logout { 
                color: #fb7185; 
                text-decoration: none; 
                margin-left: 10px; 
                border: 1px solid rgba(251, 113, 133, 0.4); 
                padding: 3px 12px; 
                border-radius: 6px; 
                transition: var(--transition);
            }
            .btn-logout:hover { 
                background-color: #fb7185; 
                color: white; 
                border-color: #fb7185;
            }

            /* Sub Header */
            .sub-header { 
                background: var(--white);
                padding: 10px 25px; 
                font-weight: 600; 
                color: var(--primary-dark); 
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                display: flex;
                align-items: center;
            }
            .sub-header::before {
                content: '';
                width: 4px;
                height: 18px;
                background: var(--accent-color);
                margin-right: 10px;
                border-radius: 4px;
            }

            /* Navbar */
            .navbar-custom { 
                background-color: var(--primary-main); 
                padding: 0; 
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            }
            .nav-link-custom { 
                color: #94a3b8; 
                padding: 14px 20px; 
                text-decoration: none; 
                display: inline-block; 
                transition: var(--transition);
                border-bottom: 3px solid transparent;
                font-size: 0.95rem;
            }
            .nav-link-custom:hover { 
                color: var(--white); 
                background-color: rgba(255,255,255,0.05); 
            }
            .nav-link-custom.active { 
                color: var(--accent-color); 
                border-bottom-color: var(--accent-color);
                font-weight: 500;
            }
            
            /* Dropdown */
            .dropdown-menu { 
                border-radius: 8px; 
                border: none; 
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
                padding: 8px;
                margin-top: 5px;
            }
            .dropdown-item {
                border-radius: 6px;
                padding: 8px 16px;
                color: var(--text-main);
                transition: var(--transition);
            }
            .dropdown-item:hover {
                background-color: #f1f5f9;
                color: var(--accent-color);
            }
            .dropdown-item.active, .dropdown-item:active { 
                background-color: var(--accent-color); 
                color: white !important; 
                font-weight: 600 !important; 
            }

            /* Content Card */
            .content-card { 
                background: var(--white); 
                border-radius: 12px; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
                padding: 35px; 
                margin-top: 25px; 
                border-top: 5px solid var(--accent-color); 
                min-height: 500px; 
            }
            .page-title { 
                color: var(--primary-dark); 
                font-weight: 700; 
                text-align: center; 
                font-size: 1.5rem; 
                margin-bottom: 30px; 
                letter-spacing: -0.5px;
            } 
            
            /* Table Styles */
            .table-custom {
                border-collapse: separate;
                border-spacing: 0;
                width: 100%;
            }
            .table-custom th { 
                background-color: #f8fafc; 
                color: var(--text-muted); 
                text-align: center; 
                vertical-align: middle; 
                font-weight: 600;
                font-size: 0.85rem; 
                padding: 15px 10px; 
                border-bottom: 2px solid #e2e8f0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .table-custom td { 
                vertical-align: middle; 
                border-bottom: 1px solid #f1f5f9; 
                padding: 14px 10px; 
                font-size: 0.95rem; 
                background-color: var(--white) !important; 
            }
            .table-custom tbody tr:hover td {
                background-color: #f8fafc !important;
                color: var(--accent-color);
            }
            
            .td-center { text-align: center; }
            .td-right { text-align: right; font-family: 'Courier New', Courier, monospace; font-weight: 600; }
            .td-left { text-align: left; }
            
            /* Buttons */
            .btn-add { 
                background-color: var(--primary-dark); 
                color: white; 
                border: none; 
                padding: 10px 24px; 
                border-radius: 8px; 
                font-weight: 600; 
                text-decoration: none; 
                display: inline-block;
                transition: var(--transition);
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            }
            .btn-add:hover { 
                background-color: var(--accent-color); 
                color: white; 
                transform: translateY(-1px);
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            }
            
            .action-btn { 
                border: none; 
                background-color: #f1f5f9; 
                border-radius: 6px;
                width: 32px;
                height: 32px;
                cursor: pointer; 
                font-size: 0.9rem; 
                margin: 0 3px;
                transition: var(--transition);
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .btn-edit { color: #f59e0b; } 
            .btn-edit:hover { background-color: #f59e0b; color: white; }
            
            .btn-delete { color: #ef4444; } 
            .btn-delete:hover { background-color: #ef4444; color: white; }
            
            .btn-detail { color: #3b82f6; }
            .btn-detail:hover { background-color: #3b82f6; color: white; }

            /* Scrollbar */
            ::-webkit-scrollbar { width: 8px; }
            ::-webkit-scrollbar-track { background: #f1f5f9; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        </style>
    </head>
    <body>
        </body>
    </html>