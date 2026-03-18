import json
import os

PERMISSIONS_FILE = '/app/permissions.json'

class PermissionService:
    _permissions = None

    @classmethod
    def load_permissions(cls):
        if cls._permissions is not None:
            return cls._permissions
            
        if not os.path.exists(PERMISSIONS_FILE):
            cls._permissions = {}
            return cls._permissions

        try:
            with open(PERMISSIONS_FILE, 'r') as f:
                cls._permissions = json.load(f)
        except Exception as e:
            print(f"Error loading permissions: {e}")
            cls._permissions = {}
            
        return cls._permissions

    @classmethod
    def save_permissions(cls, permissions):
        try:
            with open(PERMISSIONS_FILE, 'w') as f:
                json.dump(permissions, f, indent=4)
            cls._permissions = permissions
            return True
        except Exception as e:
            print(f"Error saving permissions: {e}")
            return False

    @classmethod
    def get_command_permissions(cls, command_name):
        perms = cls.load_permissions()
        return perms.get(command_name, {'roles': [], 'allow_all': False}) # Default structure

    @classmethod
    def check_permission(cls, command_name, user_roles, is_admin=False):
        # Admin always has permission
        if is_admin:
            return True
            
        perms = cls.get_command_permissions(command_name)
        
        # If allow_all is true, everyone can use it
        if perms.get('allow_all', False):
            return True
            
        allowed_roles = perms.get('roles', [])
        
        # If no roles defined and not allow_all, default to Admin only (so false here)
        if not allowed_roles:
            return False
            
        # Check if user has any of the allowed roles
        user_role_ids = [str(r.id) for r in user_roles]
        
        # Check intersection
        if set(allowed_roles) & set(user_role_ids):
            return True
            
        return False
